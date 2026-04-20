<?php

namespace Drupal\data_source\Service;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileUrlGenerator;
use Drupal\Component\Utility\Xss;

/**
 * Generates file links from file IDs.
 */
class FileLinkGenerator {

  protected FileUrlGenerator $fileUrlGenerator;

  public function __construct(FileUrlGenerator $fileUrlGenerator) {
    $this->fileUrlGenerator = $fileUrlGenerator;
  }

  /**
   * Generates a renderable link to a file.
   *
   * @param int $file_id
   *   The ID of the file entity.
   *
   * @return array
   *   A render array with the file link or an error message.
   */
  public function getLink(int $file_id): array {
    $file = File::load($file_id);

    if ($file) {
      $url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
      return Link::fromTextAndUrl(
        $file->getFilename(),
        Url::fromUri($url, ['attributes' => ['target' => '_blank']])
      )->toRenderable();
    }

    return [
      '#markup' => t('File not found'),
    ];
  }

  public function renderLink(int $file_id){
    $file = File::load($file_id);

    if ($file) {
      $url = $file->createFileUrl(); // or use file_url_generator if preferred
      $filename = Xss::filter($file->getFilename());
      $explode_fn = explode('.', $filename);
      if (isset($explode_fn[0])) {
        $short_filename = substr($explode_fn[0], 0,10).'...'.$explode_fn[1];
      }else{
        $short_filename = '-';
      }
      return '<a title="click to open file in new tab" class="btn btn-info btn-xs-text link-file" href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $short_filename . '</a>';
    }

    return 'File not found.';
  }

}
