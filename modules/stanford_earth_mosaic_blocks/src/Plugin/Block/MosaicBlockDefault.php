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
              '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/studentmain.jpg',
            ],
            'is_right' => TRUE,
            'quote' => $this->t('We face enormous hurdles as a society in dealing with a changing climate, over extraction of resources, and biodiversity loss. Still, I remain optimistic about our ability to identify and design solutions.'),
            'items' => [
              [
                'image' => [
                  '#alt' => $this->t('A smiling woman wearing a lab coat.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/student1.jpg',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('A smiling blonde woman.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/student2.jpg',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('A couple of students out in examining plant leaves.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/student3.jpg',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('A dark haired woman with a camera in a bamboo forest.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/student4.jpg',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
            ],
            //'link' => '/community/students',
            'link' => '/news/spotlights',
            'title' => 'Students',
          ],
          [
            'cite_name' => $this->t('Biondo Biondi. '),
            'cite_title' => $this->t('Geophysics Professor.'),
            'classes' => 'photo-mosaic--thumbs-up',
            'description' => $this->t('We discover and teach.'),
            'image' => [
              '#alt' => $this->t('A group of clapping graduating students wearing gowns and caps.'),
              '#theme' => 'image',
              '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/facmain.jpg',
            ],
            'is_right' => FALSE,
            'quote' => $this->t('I\'m fascinated by the way our world works. I use technology to make visible what is not visible to the naked eye.'),
            'items' => [
              [
                'image' => [
                  '#alt' => $this->t('A side profile headshot of a dark haired man.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/fac1.jpg',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('A smiling man with glasses.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/fac2.jpg',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('A smiling blonde woman at a chalkboard.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/fac3.jpg',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('A smiling lady.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/fac4.jpg',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
            ],
            // 'link' => '/community/faculty',
            'link' => 'https://pangea.stanford.edu/people/all?tmp_associate_type=regular&tmp_affiliation=all&field_ses_phd_student_value=All&name=',
            'title' => $this->t('Faculty'),
          ],
          [
            'classes' => 'photo-mosaic--thumbs-down-alt',
            'description' => $this->t('We connect and keep learning.'),
            'image' => [
              '#alt' => $this->t('A group of happy students at a table.'),
              '#theme' => 'image',
              '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/alummain.jpg',
            ],
            'is_right' => FALSE,
            'items' => [
              [
                'image' => [
                  '#alt' => $this->t('Smiling woman wearing pink dress.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/alum1.jpg',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('Couple hugging and looking through glass at the aquarium.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/alum2.jpg',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('Smiling man with plant.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/alum3.jpg',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
              [
                'image' => [
                  '#alt' => $this->t('Smiling man in a pink collard shirt.'),
                  '#theme' => 'image',
                  '#uri' => drupal_get_path('module', 'stanford_earth_mosaic_blocks') . '/img/alum4.jpg',
                ],
                // 'link' => 'https://www.stanford.edu',
              ],
            ],
            // 'link' => '/community/alumni',
            'link' => 'https://pangea.stanford.edu/alumni',
            'title' => $this->t('Alumni'),
          ],
        ],
      ],
    );
  }

}
