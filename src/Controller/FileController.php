<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Finder\Finder;
use ZipArchive;


class FileController extends AbstractController
{

  private function dateToPath(?string $date): string
  {
    $dt = $date ? \DateTimeImmutable::createFromFormat('Y-m-d', $date) : new \DateTimeImmutable();
    if (!$dt) {
      throw new \InvalidArgumentException("Invalid date format, expected YYYY-MM-DD");
    }
    return $dt->format('Y/m/d');
  }

  private function resolveUserKey(Request $request): string
  {
    $userKey = trim($request->headers->get('X-User-Key', 'legacy'));
    if (!preg_match('/^[a-zA-Z0-9_-]{3,64}$/', $userKey)) {
      throw new \RuntimeException('Invalid user key', 401);
    }

    $allowedKeys = array_values(array_filter(array_map(
        'trim',
        explode(',', (string) $this->getParameter('user_keys'))
    )));

    if (!in_array($userKey, $allowedKeys, true)) {
      throw new \RuntimeException('Unauthorized user key', 401);
    }

    return $userKey;
  }

  private function storageDirForUser(Request $request): string
  {
    $userKey = $this->resolveUserKey($request);
    $baseDir = $this->getParameter('uploads_dir');

    return $baseDir . DIRECTORY_SEPARATOR . 'users' . DIRECTORY_SEPARATOR . $userKey;
  }

  private function extractionRootForUser(Request $request): string
  {
    $userKey = $this->resolveUserKey($request);
    return dirname(__DIR__, 2)
        . DIRECTORY_SEPARATOR . 'var'
        . DIRECTORY_SEPARATOR . 'extractions'
        . DIRECTORY_SEPARATOR . $userKey;
  }

  private function unauthorizedUserResponse(\RuntimeException $e): ?JsonResponse
  {
    if ($e->getCode() !== 401) {
      return null;
    }

    return $this->json([
        'result' => 'error',
        'message' => $e->getMessage(),
    ], 401);
  }

  #[Route('/upload', name: 'upload', methods: ['POST'])]
  public function uploadFile(Request $request): Response
  {
    /** @var UploadedFile|UploadedFile[]|null $files */
    $files = $request->files->get('files') ?? [];
    if (!is_array($files)) $files = [$files];
    $date = $request->request->get('date'); // ex: "2026-01-14"

    if (!$files) {
      return $this->json(['result' => 'error', 'message' => 'No files provided'], 400);
    }

    try {
      $baseDir = $this->storageDirForUser($request);
    } catch (\RuntimeException $e) {
      if ($response = $this->unauthorizedUserResponse($e)) {
        return $response;
      }
      throw $e;
    }

    $saved = [];

    foreach ($files as $file) {
      $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
      $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
      $ext = strtolower((string) $file->getClientOriginalExtension());

      if ($ext === '' || $ext === 'bin') {
        $guessed = $file->guessExtension(); // basé sur le contenu (mime)
        if ($guessed) {
          $ext = $guessed;
        } else {
          // fallback via mime
          $mime = $file->getMimeType();
          $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'bin',
          };
        }
      }
      $newName = uniqid('', true) . '-' . $safeName . '.' . $ext;

      $dayPath = $this->dateToPath($date);
      $targetDir = $baseDir . DIRECTORY_SEPARATOR . $dayPath;

      if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0777, true);
      }

      $file->move($targetDir, $newName);
      $saved[] = $dayPath . '/' . $newName;
    }

    return $this->json([
        'result' => 'success',
        'date' => $date,
        'files' => $saved
    ]);
  }

  #[Route('/api/photos/month', name: 'api_photos_by_month', methods: ['GET'])]
  public function listDaysWithPhotos(Request $request): Response
  {
    $month = $request->query->get('month'); // "YYYY-MM"
    if (!$month || !preg_match('/^\d{4}-\d{2}$/', $month)) {
      return $this->json(['result' => 'error', 'message' => 'Missing/invalid month (YYYY-MM)'], 400);
    }

    $dt = \DateTimeImmutable::createFromFormat('Y-m', $month);
    if (!$dt) {
      return $this->json(['result' => 'error', 'message' => 'Invalid month'], 400);
    }

    $year = $dt->format('Y');
    $m = $dt->format('m');

    try {
      $baseDir = $this->storageDirForUser($request);
    } catch (\RuntimeException $e) {
      if ($response = $this->unauthorizedUserResponse($e)) {
        return $response;
      }
      throw $e;
    }
    $monthDir = $baseDir . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $m;

    if (!is_dir($monthDir)) {
      return $this->json([
          'result' => 'success',
          'month' => $month,
          'daysWithPhotos' => [],
          'countsByDay' => new \stdClass(),
      ]);
    }

    // On ne scanne QUE les sous-dossiers "01..31"
    $daysWithPhotos = [];
    $countsByDay = [];

    foreach (new \DirectoryIterator($monthDir) as $dayEntry) {
      if ($dayEntry->isDot() || !$dayEntry->isDir()) continue;

      $dayName = $dayEntry->getFilename(); // "14"
      if (!preg_match('/^\d{2}$/', $dayName)) continue;

      $dayPath = $monthDir . DIRECTORY_SEPARATOR . $dayName;

      // Compte rapide des images
      $finder = new Finder();
      $finder->files()
          ->in($dayPath)
          ->depth('== 0')
          ->name('/\.(jpg|jpeg|png|webp)$/i');

      $count = $finder->count();
      if ($count > 0) {
        $dayInt = (int)$dayName;
        $daysWithPhotos[] = $dayInt;
        $countsByDay[(string)$dayInt] = $count;
      }
    }

    sort($daysWithPhotos);

    return $this->json([
        'result' => 'success',
        'month' => $month,
        'daysWithPhotos' => $daysWithPhotos,
        'countsByDay' => $countsByDay,
    ]);
  }


  #[Route('/api/photos', name: 'api_photos_by_day', methods: ['GET'])]
  public function listPhotosByDay(Request $request): Response
  {
    $date = $request->query->get('date'); // YYYY-MM-DD
    if (!$date) {
      return $this->json(['result' => 'error', 'message' => 'Missing date (YYYY-MM-DD)'], 400);
    }

    try {
      $dayPath = $this->dateToPath($date); // YYYY/MM/DD
    } catch (\InvalidArgumentException $e) {
      return $this->json(['result' => 'error', 'message' => $e->getMessage()], 400);
    }

    try {
      $baseDir = $this->storageDirForUser($request);
    } catch (\RuntimeException $e) {
      if ($response = $this->unauthorizedUserResponse($e)) {
        return $response;
      }
      throw $e;
    }
    $dir = $baseDir . DIRECTORY_SEPARATOR . $dayPath;

    if (!is_dir($dir)) {
      return $this->json([
          'result' => 'success',
          'date' => $date,
          'count' => 0,
          'photos' => [],
      ]);
    }

    $finder = new Finder();
    $finder->files()
        ->in($dir)
        ->depth('== 0')
        ->name('/\.(jpg|jpeg|png|webp)$/i')
        ->sortByName();

    $photos = [];
    foreach ($finder as $file) {
      $filename = $file->getFilename();
      $relativePath = $dayPath . '/' . $filename;

      $photos[] = [
          'name' => $filename,
          'path' => $relativePath,

          'url'  => '/api/photo?path=' . rawurlencode($relativePath),
      ];
    }

    return $this->json([
        'result' => 'success',
        'date' => $date,
        'count' => count($photos),
        'photos' => $photos,
    ]);
  }

  #[Route('/api/extraction/months', name: 'api_extraction_months', methods: ['POST'])]
  public function extractMonths(Request $request): Response
  {
    if (!class_exists(ZipArchive::class)) {
      return $this->json([
          'result' => 'error',
          'message' => 'ZipArchive is not available on this server',
      ], 500);
    }

    $payload = json_decode($request->getContent(), true);
    $months = $payload['months'] ?? null;

    if (!is_array($months) || count($months) === 0) {
      return $this->json([
          'result' => 'error',
          'message' => 'Missing months array',
      ], 400);
    }

    $months = array_values(array_unique(array_filter($months, 'is_string')));
    foreach ($months as $month) {
      if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        return $this->json([
            'result' => 'error',
            'message' => 'Invalid month format, expected YYYY-MM',
        ], 400);
      }
    }

    try {
      $baseDir = $this->storageDirForUser($request);
      $extractionRoot = $this->extractionRootForUser($request);
    } catch (\RuntimeException $e) {
      if ($response = $this->unauthorizedUserResponse($e)) {
        return $response;
      }
      throw $e;
    }
    $batchDirName = 'extraction_' . (new \DateTimeImmutable())->format('Ymd_His');
    $batchDir = $extractionRoot . DIRECTORY_SEPARATOR . $batchDirName;

    if (!is_dir($batchDir)) {
      @mkdir($batchDir, 0777, true);
    }

    $items = [];
    $zipName = $batchDirName . '.zip';
    $zipPath = $batchDir . DIRECTORY_SEPARATOR . $zipName;
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
      return $this->json([
          'result' => 'error',
          'message' => 'Unable to create extraction zip',
      ], 500);
    }

    foreach ($months as $month) {
      $dt = \DateTimeImmutable::createFromFormat('Y-m', $month);
      if (!$dt) {
        continue;
      }

      $year = $dt->format('Y');
      $monthNumber = $dt->format('m');
      $monthDir = $baseDir . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $monthNumber;
      if (!is_dir($monthDir)) {
        $items[] = [
            'month' => $month,
            'photoCount' => 0,
            'status' => 'empty',
        ];
        continue;
      }

      $finder = new Finder();
      $finder->files()
          ->in($monthDir)
          ->name('/\.(jpg|jpeg|png|webp)$/i')
          ->sortByName();

      if ($finder->count() === 0) {
        $items[] = [
            'month' => $month,
            'photoCount' => 0,
            'status' => 'empty',
        ];
        continue;
      }

      $photoCount = 0;
      $countsByDay = [];
      foreach ($finder as $file) {
        $day = $file->getRelativePath();
        if (!preg_match('/^\d{2}$/', $day)) {
          $day = '00';
        }

        $countsByDay[$day] = ($countsByDay[$day] ?? 0) + 1;
        $index = str_pad((string) $countsByDay[$day], 3, '0', STR_PAD_LEFT);
        $extension = strtolower($file->getExtension()) ?: 'jpg';
        $zipFilename = sprintf('%s/%s_%s_%s_%s.%s', $month, $day, $monthNumber, $year, $index, $extension);

        $zip->addFile($file->getRealPath(), $zipFilename);
        $photoCount++;
      }

      $items[] = [
          'month' => $month,
          'photoCount' => $photoCount,
          'status' => 'ready',
      ];
    }

    $zip->close();

    return $this->json([
        'result' => 'success',
        'directory' => $batchDir,
        'zipName' => $zipName,
        'downloadUrl' => '/api/extraction/download?batch=' . rawurlencode($batchDirName) . '&file=' . rawurlencode($zipName),
        'items' => $items,
    ]);
  }

  #[Route('/api/extraction/download', name: 'api_extraction_download', methods: ['GET'])]
  public function downloadExtraction(Request $request): Response
  {
    $batch = $request->query->get('batch');
    $file = $request->query->get('file');

    if (!$batch || !$file) {
      return $this->json(['result' => 'error', 'message' => 'Missing batch or file'], 400);
    }

    if (
      !preg_match('/^extraction_\d{8}_\d{6}$/', $batch) ||
      !preg_match('/^(extraction_\d{8}_\d{6}|\d{4}-\d{2})\.zip$/', $file)
    ) {
      return $this->json(['result' => 'error', 'message' => 'Invalid extraction file'], 400);
    }

    try {
      $extractionRoot = $this->extractionRootForUser($request);
    } catch (\RuntimeException $e) {
      if ($response = $this->unauthorizedUserResponse($e)) {
        return $response;
      }
      throw $e;
    }
    $zipPath = $extractionRoot . DIRECTORY_SEPARATOR . $batch . DIRECTORY_SEPARATOR . $file;

    if (!is_file($zipPath)) {
      return $this->json(['result' => 'error', 'message' => 'Extraction not found'], 404);
    }

    return $this->file($zipPath, $file);
  }



  #[Route('/api/photo', name: 'api_photo_get', methods: ['GET'])]
  public function getPhoto(Request $request): Response
  {
    $path = $request->query->get('path');
    if (!$path) {
      return $this->json(['result' => 'error', 'message' => 'Missing path'], 400);
    }
    if (str_contains($path, '..')) {
      return $this->json(['result' => 'error', 'message' => 'Invalid path'], 400);
    }

    try {
      $baseDir = $this->storageDirForUser($request);
    } catch (\RuntimeException $e) {
      if ($response = $this->unauthorizedUserResponse($e)) {
        return $response;
      }
      throw $e;
    }

    $full = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

    if (!is_file($full)) {
      return $this->json(['result' => 'error', 'message' => 'Not found'], 404);
    }

    return $this->file($full);
  }

  #[Route('/api/photo', name: 'api_photo_delete', methods: ['DELETE'])]
  public function deletePhoto(Request $request): Response
  {
    $path = $request->query->get('path');
    if (!$path) {
      return $this->json(['result' => 'error', 'message' => 'Missing path'], 400);
    }
    if (str_contains($path, '..')) {
      return $this->json(['result' => 'error', 'message' => 'Invalid path'], 400);
    }

    try {
      $baseDir = $this->storageDirForUser($request);
    } catch (\RuntimeException $e) {
      if ($response = $this->unauthorizedUserResponse($e)) {
        return $response;
      }
      throw $e;
    }

    $full = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

    if (!is_file($full)) {
      return $this->json(['result' => 'error', 'message' => 'Not found'], 404);
    }

    if (!@unlink($full)) {
      return $this->json(['result' => 'error', 'message' => 'Unable to delete photo'], 500);
    }

    return $this->json(['result' => 'success']);
  }

}
