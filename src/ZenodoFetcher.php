<?php

namespace Drupal\sodanodo;

use Drupal\Core\Database\Connection;
use Drupal\node\Entity\Node;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Class ZenodoFetcher.
 */
class ZenodoFetcher {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new ZenodoFetcher object.
   */
  public function __construct(ClientInterface $http_client, Connection $database) {
    $this->httpClient = $http_client;
    $this->database = $database;
  }

  /**
   * Fetches publications from Zenodo and stores them as HTML.
   */
  /**
   * Fetches publications from Zenodo and stores them as HTML.
   */
  public function fetchAndStorePublications() {
    $community_id = 'soda'; // Replace with your Zenodo community ID.
    $base_url = "https://zenodo.org/api/communities/{$community_id}/records";
    $all_hits = [];
    $page = 1;

    \Drupal::logger('zenodo_publications')->notice('Starting Zenodo fetch for community: ' . $community_id);

    while ($page <= 10) {
      $url = $base_url . '?page=' . $page;

      try {
        $response = $this->httpClient->get($url);
        $data = json_decode($response->getBody(), TRUE);

        \Drupal::logger('zenodo_publications')->debug('Fetched page ' . $page . ' with ' . count($data['hits']['hits']) . ' hits.');

        if (empty($data['hits']['hits'])) {
          \Drupal::logger('zenodo_publications')->notice('No more hits found. Stopping pagination.');
          break;
        }

        $all_hits = array_merge($all_hits, $data['hits']['hits']);
        $page++;
      } catch (RequestException $e) {
        \Drupal::logger('zenodo_publications')->error('Zenodo API request failed for page ' . $page . ': ' . $e->getMessage());
        break;
      }
    }

    if (!empty($all_hits)) {
      $html = $this->generateHtmlList($all_hits);
      $this->storeHtml($html);
      \Drupal::logger('zenodo_publications')->notice('Successfully stored HTML list with ' . count($all_hits) . ' publications.');
    } else {
      \Drupal::logger('zenodo_publications')->warning('No publications found in Zenodo response.');
    }
  }
 

  /**
   * Generates an HTML list from publications.
   */
  protected function generateHtmlList(array $publications) {
    $team_names = ['Gnyp, Anna', 'Tharandt, Louise','Stricker, Martin','Schäffer, Johannes',
        'Reichert, Rebekka','Zöllner. Garbiele','Markert, Michael','Leyrer, Katharina',
        'Amann, Kai','Nasarek, Robert','Neubauer, Julia','Hastik, Canan','Schwenk, Gudrun',
        'Fichtner, Mark','Andraschke, Udo','Zinnen, Mathias','Cremerius, Julian','Wiesing, Tom',
        'jcremerius','Schwenk, Gudrun A.', 'Stocker, Lea', 'Louise Tharandt', 'Wagner, Lucia'];
    
    $html = '<div class="zenodo-publications-list">';
    foreach ($publications as $pub) {
      // Extract creator names from the publication
      $creator_names = array_column($pub['metadata']['creators'], 'name');

      // Check if any creator is in the team_names list
      $include = false;
      foreach ($creator_names as $creator) {
        if (in_array($creator, $team_names)) {
          $include = true;
          break;
        }
      }
      $creator_names = array_map(function($name) {
        $parts = explode(', ', $name);
        if (count($parts) === 2) {
          return $parts[1] . ' ' . $parts[0];
        }
        return $name;
      }, $creator_names);
      if ($include) {
        $html .= sprintf(
          '<div style="margin-bottom: 0.1em"><strong><a href="%s" target="_blank">%s</a></strong><small>(%s) %s</small></div>',
          $pub['links']['self_html'],
          $pub['metadata']['title'],
          date("d.m.Y", strtotime($pub['metadata']['publication_date'])),
          implode(', ', $creator_names),
          //$pub['metadata']['doi'] ?? 'N/A',
          // $pub['metadata']['publication_date'] ?? 'N/A',
          //$pub['metadata']['description'] ?? '',
        
        );
      }
    }
    $html .= '</div>';
    return $html;
  }

  /**
   * Stores the HTML in a Drupal node.
   */
  protected function storeHtml($html) {
    $node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['title' => 'Publikationsliste']);

    $node = reset($node) ?: Node::create(['title' => 'Publikationsliste']);

    //$node->set('title', 'Zenodo Publications');
    $node->set('body', ['value' => $html, 'format' => 'full_html']);
    $node->save();
  }
}
