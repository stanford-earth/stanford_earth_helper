<?php

namespace Drupal\stanford_earth_mosaic_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url\Link;

/**
 * Provides a 'Hello' Block.
 *
 * @Block(
 *   id = "mosaic_block_default",
 *   admin_label = @Translation("Mosaic Block - 1"),
 * )
 */
class MosaicBlockDefault extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return array(
      '#type' => 'pattern',
      '#id' => 'section_photo_mosaic_quotes',
      '#fields' => [
        'dash_under' => FALSE,
        'is_centered' => FALSE,
        'is_featured' => FALSE,
        'subhead' => '<p>' . $this->t('We are scientists! Undergraduates, graduate students, professors, educational staff, and alumni working professionals. We build community in our field trips, classes, and cocurriculars. We care about the Earth and making its resources available to people across the globe now and in the future.') . '</p>',
        'superhead' => $this->t('Our Community'),
        'tiles' => [
          [
            'cite_name' => $this->t('Elsa M. Ordway'),
            'cite_title' => $this->t('Ph.D. Candidate'),
            'classes' => 'photo-mosaic--thumbs-down-quote',
            'description' => $this->t('Together, we pursue science and build lifelong bonds.'),
            'image' => [
              '#alt' => $this->t('A collection of happy students'),
              '#theme' => 'image',
              '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/studentmain.png',
            ],
            'is_right' => TRUE,
            'quote' => $this->t('We face enormous hurdles as a society in dealing with a changing climate, over extraction of resources, and biodiversity loss. Still, I remain optimistic about our ability to identify and design solutions.'),
            'items' => [
              [
                'image' => [
                  '#alt' => $this->t('A smiling woman wearing a lab coat.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/student1.png',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('A smiling man at a computer.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/student2.png',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('A couple of students out in examining plant leaves.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/student3.png',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('A dark haired woman sitting cross legged and smiling.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/student4.png',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
            ],
            'link' => '/community/students',
            'title' => 'Students',
          ],
          [
            'cite_name' => $this->t('Biondo Biondi.'),
            'cite_title' => $this->t('Geophysics Professor.'),
            'classes' => 'photo-mosaic--thumbs-up',
            'description' => $this->t('We discover and teach.'),
            'image' => [
              '#alt' => $this->t('A group of clapping graduating students wearing gowns and caps.'),
              '#theme' => 'image',
              '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/facmain.png',
            ],
            'is_right' => FALSE,
            'quote' => $this->t('I\'m fascinated by the way our world works. I use technology to make visible what is not visible to the naked eye.'),
            'items' => [
              [
                'image' => [
                  '#alt' => $this->t('A side profile headshot of a dark haired man.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/fac1.png',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('A smiling mature woman.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/fac2.png',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('A smiling blonde woman at a chalkboard.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/fac3.png',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('A smiling lady.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/fac4.png',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
            ],
            'link' => '/community/faculty',
            'title' => $this->t('Faculty'),
          ],
          [
            'classes' => 'photo-mosaic--thumbs-down-alt',
            'description' => $this->t('We connect and keep learning.'),
            'image' => [
              '#alt' => $this->t('A group of happy students at a table.'),
              '#theme' => 'image',
              '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/alummain.png',
            ],
            'is_right' => FALSE,
            'items' => [
              [
                'image' => [
                  '#alt' => $this->t('Smiling woman wearing pink dress.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/alum1.png',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('Couple hugging and looking through glass at the aquarium.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/alum2.png',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('Blonde woman in a blue shirt.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/alum3.png',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('Smiling man in a pink collard shirt.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/alum4.png',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
            ],
            'link' => '/community/alumni',
            'title' => $this->t('Alumni'),
          ],
        ],
      ],
    );
  }

}
