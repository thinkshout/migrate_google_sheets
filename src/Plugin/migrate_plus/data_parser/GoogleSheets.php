<?php

namespace Drupal\migrate_google_sheets\Plugin\migrate_plus\data_parser;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus\Plugin\migrate_plus\data_parser\Json;
use GuzzleHttp\Exception\RequestException;

/**
 * Obtain Google Sheet data for migration.
 *
 * @DataParser(
 *   id = "google_sheets",
 *   title = @Translation("Google Sheets")
 * )
 */
class GoogleSheets extends Json implements ContainerFactoryPluginInterface {

  /**
   * @var array
   */
  protected $headers = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function getSourceData($url) {
    // Since we're being explicit about the data location, we can return the
    // array without calling getSourceIterator to get an iterator to find the
    // correct values.
    try {
      $response = $this->getDataFetcherPlugin()->getResponseContent($url);
      // Response returns json wrapped in a function call, first 47 and last 2 chars.
      $response = substr($response, 47, (strlen($response) - 47 - 2));
      // The TRUE setting means decode the response into an associative array.
      $array = json_decode($response, TRUE);

      // For Google Sheets, the actual row data lives under table->rows.
      if (isset($array['table']) && isset($array['table']['rows'])) {
        if (isset($array['table']['cols']) && $array['table']['parsedNumHeaders'] > 0) {
          // Set headers based on column labels.
          $columns = array_column($array['table']['cols'], 'label');
        } else {
          // Set headers from first row.
          $first_row = array_shift($array['table']['rows']);
          $columns = array_column($first_row['c'], 'v');
        }
        $this->headers = array_map(function($col) {
          return strtolower($col);
        }, $columns);

        $array = $array['table']['rows'];
      } else {
        $array = [];
      }

      return $array;
    }
    catch (RequestException $e) {
      throw new MigrateException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function fetchNextRow() {
    $current = $this->iterator->current();
    if ($current) {
      foreach ($this->fieldSelectors() as $field_name => $selector) {
        // Actual values are stored in c[<column index>]['v'].
        $column_index = array_search(strtolower($selector), $this->headers);
        if ($column_index >= 0) {
          $this->currentItem[$field_name] = $current['c'][$column_index]['v'];
        } else {
          $this->currentItem[$field_name] = NULL;
        }
      }
      $this->iterator->next();
    }
  }

}
