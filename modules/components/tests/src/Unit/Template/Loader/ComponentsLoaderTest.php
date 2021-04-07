<?php

namespace Drupal\Tests\components\Unit\Template\Loader;

use Drupal\components\Template\Loader\ComponentsLoader;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\components\Template\Loader\ComponentsLoader
 * @group components
 */
class ComponentsLoaderTest extends UnitTestCase {

  /**
   * The components registry service.
   *
   * @var \Drupal\components\Template\ComponentsRegistry|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $componentsRegistry;

  /**
   * The system under test.
   *
   * @var \Drupal\components\Template\Loader\ComponentsLoader
   */
  protected $systemUnderTest;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Set up mocked services.
    $this->componentsRegistry = $this->createMock('\Drupal\components\Template\ComponentsRegistry');
    $this->componentsRegistry
      ->method('getNamespaces')
      ->willReturn([]);
    $this->componentsRegistry
      ->method('getActiveThemeName')
      ->willReturn('constructorTheme');

    $this->systemUnderTest = new ComponentsLoader($this->componentsRegistry);
  }

  /**
   * Gets the value of a protected or private property of an object.
   *
   * @param object $obj
   *   The instantiated object (or null for static methods.)
   * @param string $property
   *   The property to get.
   *
   * @return mixed
   *   The value of the property.
   *
   * @throws \ReflectionException
   */
  public function getProtectedProperty(object $obj, string $property) {
    $propertyUnderTest = new \ReflectionProperty($obj, $property);
    $propertyUnderTest->setAccessible(TRUE);
    return $propertyUnderTest->getValue($obj);
  }

  /**
   * Tests activating the proper namespaces if the active theme has changed.
   *
   * @param array $getNamespaces
   *   A list of namespaces to return by ComponentsRegistry::getNamespaces().
   * @param array $getActiveThemeName
   *   A list of active themes to return by
   *   ComponentsRegistry::getActiveThemeName().
   * @param array $expected
   *   An array of expected data.
   *
   * @covers ::init
   *
   * @dataProvider providerTestInit
   *
   * @throws \ReflectionException
   * @throws \Twig\Error\LoaderError
   */
  public function testInit(array $getNamespaces, array $getActiveThemeName, array $expected) {
    $this->componentsRegistry = $this->createMock('\Drupal\components\Template\ComponentsRegistry');
    $this->componentsRegistry
      ->expects($this->atLeastOnce())
      ->method('getNamespaces')
      ->willReturn(...$getNamespaces);
    $this->componentsRegistry
      ->expects($this->atLeastOnce())
      ->method('getActiveThemeName')
      ->willReturn(...$getActiveThemeName);

    $this->systemUnderTest = new ComponentsLoader($this->componentsRegistry);

    foreach ($expected as $expectedData) {
      $this->systemUnderTest->init();

      // Check $this->activeTheme.
      $activeTheme = $this->getProtectedProperty($this->systemUnderTest, 'activeTheme');
      $this->assertEquals($expectedData['activeTheme'], $activeTheme, $this->getName() . '; checking activeTheme');
      // Check $this->paths.
      $paths = $this->getProtectedProperty($this->systemUnderTest, 'paths');
      $this->assertEquals($expectedData['paths'], $paths, $this->getName() . '; checking paths');
    }
  }

  /**
   * Data provider for testInit().
   *
   * @see testInit()
   */
  public function providerTestInit(): array {
    return [
      'loads the activeTheme namespaces' => [
        'getNamespaces' => [
          ['sol' => ['/themes/sol/components', '/themes/sol/extras']],
        ],
        'getActiveThemeName' => ['sol'],
        'expected' => [
          [
            'activeTheme' => 'sol',
            'paths' => [
              'sol' => [
                '/themes/sol/components',
                '/themes/sol/extras',
              ],
            ],
          ],
        ],
      ],
      'paths are the same if activeTheme is the same' => [
        'getNamespaces' => [
          ['sol' => ['/themes/sol/components', '/themes/sol/extras']],
        ],
        'getActiveThemeName' => ['sol', 'sol'],
        'expected' => [
          [
            'activeTheme' => 'sol',
            'paths' => [
              'sol' => [
                '/themes/sol/components',
                '/themes/sol/extras',
              ],
            ],
          ],
          [
            'activeTheme' => 'sol',
            'paths' => [
              'sol' => [
                '/themes/sol/components',
                '/themes/sol/extras',
              ],
            ],
          ],
        ],
      ],
      'new namespaces are loaded when activeTheme changes' => [
        'getNamespaces' => [
          ['sol' => ['/themes/sol/components', '/themes/sol/extras']],
          ['earth' => ['/themes/earth/components', '/themes/earth/extras']],
        ],
        'getActiveThemeName' => ['sol', 'earth'],
        'expected' => [
          [
            'activeTheme' => 'sol',
            'paths' => [
              'sol' => [
                '/themes/sol/components',
                '/themes/sol/extras',
              ],
            ],
          ],
          [
            'activeTheme' => 'earth',
            'paths' => [
              'earth' => [
                '/themes/earth/components',
                '/themes/earth/extras',
              ],
            ],
          ],
        ],
      ],
    ];
  }

}
