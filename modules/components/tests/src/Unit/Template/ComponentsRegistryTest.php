<?php

namespace Drupal\Tests\components\Unit\Template;

use Drupal\components\Template\ComponentsRegistry;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\components\Template\ComponentsRegistry
 * @group components
 */
class ComponentsRegistryTest extends UnitTestCase {

  /**
   * The logger channel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannel|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $loggerChannel;

  /**
   * The system under test.
   *
   * @var \Drupal\components\Template\ComponentsRegistry
   */
  protected $systemUnderTest;

  /**
   * Path to the mocked drupal directory.
   *
   * @var string
   */
  protected $rootDir = '/drupal';

  /**
   * Path to the mocked modules directory.
   *
   * @var string
   */
  protected $modulesDir = '/drupal/modules';

  /**
   * Path to the mocked themes directory.
   *
   * @var string
   */
  protected $themesDir = '/drupal/themes';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Ensure \Drupal::root() is available.
    $container = new ContainerBuilder();
    $container->setParameter('app.root', $this->rootDir);
    // Mock LoggerChannelTrait.
    $this->loggerChannel = $this->createMock('\Drupal\Core\Logger\LoggerChannel');
    $loggerFactory = $this->createMock('\Drupal\Core\Logger\LoggerChannelFactory');
    $loggerFactory->method('get')->willReturn($this->loggerChannel);
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);
  }

  /**
   * Invokes a protected or private method of an object.
   *
   * @param object|null $obj
   *   The instantiated object (or null for static methods.)
   * @param string $method
   *   The method to invoke.
   * @param mixed $args
   *   The parameters to be passed to the method.
   *
   * @return mixed
   *   The return value of the method.
   *
   * @throws \ReflectionException
   */
  public function invokeProtectedMethod(?object $obj, string $method, ...$args) {
    // Use reflection to test a protected method.
    $methodUnderTest = new \ReflectionMethod($obj, $method);
    $methodUnderTest->setAccessible(TRUE);
    return $methodUnderTest->invokeArgs($obj, $args);
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
   * Creates a ComponentsRegistry service after the dependencies are set up.
   *
   * @param null|\Drupal\Core\Extension\ModuleExtensionList|\PHPUnit\Framework\MockObject\MockObject $moduleExtensionList
   *   The mocked module extension list service.
   * @param null|\Drupal\Core\Extension\ThemeExtensionList|\PHPUnit\Framework\MockObject\MockObject $themeExtensionList
   *   The mocked theme extension list service.
   * @param null|\Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit\Framework\MockObject\MockObject $moduleHandler
   *   The mocked module handler service.
   * @param null|\Drupal\Core\Theme\ThemeManagerInterface|\PHPUnit\Framework\MockObject\MockObject $themeManager
   *   The mocked theme manager service.
   * @param null|\Drupal\Core\Cache\CacheBackendInterface|\PHPUnit\Framework\MockObject\MockObject $cacheBackend
   *   Cache mocked backend for storing module hook implementation information.
   *
   * @return \Drupal\components\Template\ComponentsRegistry
   *   A new ComponentsRegistry object.
   */
  public function newSystemUnderTest(
    ModuleExtensionList $moduleExtensionList = NULL,
    ThemeExtensionList $themeExtensionList = NULL,
    ModuleHandlerInterface $moduleHandler = NULL,
    ThemeManagerInterface $themeManager = NULL,
    CacheBackendInterface $cacheBackend = NULL
  ): ComponentsRegistry {
    // Generate mock objects with the minimum mocking to run the constructor.
    if (is_null($moduleExtensionList)) {
      $moduleExtensionList = $this->createMock('\Drupal\Core\Extension\ModuleExtensionList');
      $moduleExtensionList->method('getAllInstalledInfo')->willReturn([]);
    }
    if (is_null($themeExtensionList)) {
      $themeExtensionList = $this->createMock('\Drupal\Core\Extension\ThemeExtensionList');
      $themeExtensionList->method('getAllInstalledInfo')->willReturn([]);
      $themeExtensionList->method('getList')->willReturn([]);
      $themeExtensionList->method('getBaseThemes')->willReturn([]);
    }
    if (is_null($moduleHandler)) {
      $moduleHandler = $this->createMock('\Drupal\Core\Extension\ModuleHandlerInterface');
    }
    if (is_null($themeManager)) {
      $themeManager = $this->createMock('\Drupal\Core\Theme\ThemeManagerInterface');
    }
    if (is_null($cacheBackend)) {
      $cacheBackend = $this->createMock('\Drupal\Core\Cache\CacheBackendInterface');
    }

    return new ComponentsRegistry(
      $moduleExtensionList,
      $themeExtensionList,
      $moduleHandler,
      $themeManager,
      $cacheBackend
    );
  }

  /**
   * Tests getting the current active theme name.
   *
   * @covers ::getActiveThemeName
   */
  public function testGetActiveThemeName() {
    $expected = 'testThemeName';

    // Mock services.
    $activeTheme = $this->createMock('\Drupal\Core\Theme\ActiveTheme');
    $activeTheme
      ->method('getName')
      ->willReturn($expected);
    $themeManager = $this->createMock('\Drupal\Core\Theme\ThemeManagerInterface');
    $themeManager
      ->method('getActiveTheme')
      ->willReturn($activeTheme);

    $this->systemUnderTest = $this->newSystemUnderTest(NULL, NULL, NULL, $themeManager);

    $result = $this->systemUnderTest->getActiveThemeName();
    $this->assertEquals($expected, $result, $this->getName());
  }

  /**
   * Tests getting namespaces for the active theme.
   *
   * @param array $cache
   *   The $cache->get->data value.
   * @param array $themeInfo
   *   The array returned by themeExtensionList::getAllInstalledInfo().
   * @param array $getPath
   *   The PHPUnit returnValueMap array for extensionList::getPath().
   * @param array $expected
   *   The expected result.
   *
   * @throws \ReflectionException
   *
   * @covers ::init
   *
   * @dataProvider providerTestInit
   */
  public function testInit(array $cache, array $themeInfo, array $getPath, array $expected): void {
    $themeExtensionList = $this->createMock('\Drupal\Core\Extension\ThemeExtensionList');
    $themeExtensionList
      ->method('getAllInstalledInfo')
      ->willReturn($themeInfo);
    $themeExtensionList
      ->method('getPath')
      ->will($this->returnValueMap($getPath));
    $themeList = [];
    foreach ($themeInfo as $info) {
      $extension = $this->createMock('\Drupal\Core\Extension\Extension');
      $extension->method('getName')->willReturn($info['name']);
      $extension->method('getType')->willReturn('theme');
      $themeList[] = $extension;
    }
    $valueMap = [];
    foreach (array_keys($themeInfo) as $themeName) {
      $valueMap[] = [$themeList, $themeName, []];
    }
    $themeExtensionList
      ->method('getList')
      ->willReturn($themeList);
    $themeExtensionList
      ->method('getBaseThemes')
      ->will($this->returnValueMap($valueMap));

    $activeThemes = [];
    foreach (array_keys($themeInfo) as $activeThemeName) {
      $activeTheme = $this->createMock('\Drupal\Core\Theme\ActiveTheme');
      $activeTheme
        ->method('getName')
        ->willReturn($activeThemeName);
      $activeThemes[] = $activeTheme;
    }
    $themeManager = $this->createMock('\Drupal\Core\Theme\ThemeManagerInterface');
    if (!empty($activeThemes)) {
      $themeManager
        ->method('getActiveTheme')
        ->willReturn(...$activeThemes);
    }

    $cacheBackend = $this->createMock('\Drupal\Core\Cache\CacheBackendInterface');
    $cacheBackend
      ->method('get')
      ->willReturn(!empty($cache) ? (object) ['data' => $cache] : FALSE);
    if (empty($cache)) {
      $cacheBackend
        ->expects($this->exactly(1))
        ->method('set')
        ->with(
          'components:namespaces',
          $expected,
          Cache::PERMANENT,
          ['theme_registry'],
        );
    }

    $this->systemUnderTest = $this->newSystemUnderTest(NULL, $themeExtensionList, NULL, $themeManager, $cacheBackend);

    // Use reflection to test protected methods and properties.
    $this->assertFalse($this->getProtectedProperty($this->systemUnderTest, 'initialized'), $this->getName() . '; checking initialized is FALSE');
    $this->invokeProtectedMethod($this->systemUnderTest, 'init');
    $this->assertTrue($this->getProtectedProperty($this->systemUnderTest, 'initialized'), $this->getName() . '; checking initialized is TRUE');
    $result = $this->getProtectedProperty($this->systemUnderTest, 'namespaces');
    $this->assertEquals($expected, $result, $this->getName());
  }

  /**
   * Provides test data to ::testInit().
   *
   * @see testInit()
   */
  public function providerTestInit(): array {
    return [
      'gets namespaces from extension list' => [
        'cache' => [],
        'themeInfo' => [
          'activeTheme' => [
            'name' => 'Active theme',
            'type' => 'theme',
            'components' => [
              'namespaces' => [
                'components' => ['path1', 'path2'],
              ],
            ],
          ],
        ],
        'getPath' => [
          ['activeTheme', $this->themesDir . '/activeTheme'],
        ],
        'expected' => [
          'activeTheme' => [
            'components' => [
              $this->themesDir . '/activeTheme/path1',
              $this->themesDir . '/activeTheme/path2',
            ],
          ],
        ],
      ],
      'gets namespaces from cache' => [
        'cache' => [
          'activeTheme' => [
            'components' => [
              $this->themesDir . '/activeTheme/path1',
              $this->themesDir . '/activeTheme/path2',
            ],
          ],
          'baseTheme' => [
            'components' => [
              $this->themesDir . '/baseTheme/path1',
            ],
          ],
        ],
        'themeInfo' => [],
        'getPath' => [],
        'expected' => [
          'activeTheme' => [
            'components' => [
              $this->themesDir . '/activeTheme/path1',
              $this->themesDir . '/activeTheme/path2',
            ],
          ],
          'baseTheme' => [
            'components' => [
              $this->themesDir . '/baseTheme/path1',
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Tests getting namespaces for the active theme.
   *
   * @param array $namespaces
   *   The list of namespaces.
   * @param string $activeThemeName
   *   The name of the active theme.
   * @param array $cache
   *   The $cache->get->data value.
   * @param array $expected
   *   The expected result.
   *
   * @throws \ReflectionException
   *
   * @covers ::getNamespaces
   *
   * @dataProvider providerTestGetNamespaces
   */
  public function testGetNamespaces(array $namespaces, string $activeThemeName, array $cache, array $expected): void {
    $moduleHandler = $this->createMock('\Drupal\Core\Extension\ModuleHandlerInterface');
    $themeManager = $this->createMock('\Drupal\Core\Theme\ThemeManagerInterface');
    $activeTheme = $this->createMock('\Drupal\Core\Theme\ActiveTheme');
    $activeTheme
      ->method('getName')
      ->willReturn($activeThemeName);
    $themeManager
      ->method('getActiveTheme')
      ->willReturn($activeTheme);

    $cacheBackend = $this->createMock('\Drupal\Core\Cache\CacheBackendInterface');
    $cacheBackend
      ->method('get')
      ->willReturnOnConsecutiveCalls(
        (object) ['data' => $namespaces],
        !empty($cache) ? (object) ['data' => $cache] : FALSE,
      );
    if (empty($cache)) {
      $cacheBackend
        ->expects($this->exactly(1))
        ->method('set')
        ->with(
          'components:namespaces:' . $activeThemeName,
          $expected,
          Cache::PERMANENT,
          ['theme_registry'],
        );
      foreach ([$moduleHandler, $themeManager] as &$extensionHandler) {
        $extensionHandler
          ->expects($this->exactly(1))
          ->method('alter')
          ->with(
            'components_namespaces',
            $namespaces[$activeThemeName],
            $activeThemeName
          );
      }
    }

    $this->systemUnderTest = $this->newSystemUnderTest(NULL, NULL, $moduleHandler, $themeManager, $cacheBackend);

    $result = $this->systemUnderTest->getNamespaces();
    $this->assertEquals($expected, $result, $this->getName());
  }

  /**
   * Provides test data to ::testGetNamespaces().
   *
   * @see testGetNamespaces()
   */
  public function providerTestGetNamespaces(): array {
    return [
      'gets namespaces from init' => [
        'namespaces' => [
          'activeTheme' => [
            'components' => [
              $this->themesDir . '/activeTheme/path1',
              $this->themesDir . '/activeTheme/path2',
            ],
          ],
        ],
        'activeThemeName' => 'activeTheme',
        'cache' => [],
        'expected' => [
          'components' => [
            $this->themesDir . '/activeTheme/path1',
            $this->themesDir . '/activeTheme/path2',
          ],
        ],
      ],
      'gets namespaces from cache' => [
        'namespaces' => [],
        'activeThemeName' => 'activeTheme',
        'cache' => [
          'components' => [
            $this->themesDir . '/activeTheme/path1',
            $this->themesDir . '/activeTheme/path2',
          ],
        ],
        'expected' => [
          'components' => [
            $this->themesDir . '/activeTheme/path1',
            $this->themesDir . '/activeTheme/path2',
          ],
        ],
      ],
    ];
  }

  /**
   * Tests finding all the namespaces for every installed theme.
   *
   * @param array $moduleInfo
   *   The array returned by moduleExtensionList::getAllInstalledInfo().
   * @param array $themeInfo
   *   The array returned by themeExtensionList::getAllInstalledInfo().
   * @param array $getPath
   *   The PHPUnit returnValueMap array for extensionList::getPath().
   * @param array $getBaseThemes
   *   A theme-name-keyed array of return values for
   *   themeExtensionList::getBaseThemes().
   * @param array $expected
   *   The expected result.
   * @param array $expectedWarnings
   *   The list of expected warnings.
   *
   * @throws \ReflectionException
   *
   * @covers ::findNamespaces
   *
   * @dataProvider providerTestFindNamespaces
   */
  public function testFindNamespaces(array $moduleInfo, array $themeInfo, array $getPath, array $getBaseThemes, array $expected, array $expectedWarnings = []) {
    // Mock the method params with the test data.
    $moduleExtensionList = $this->createMock('\Drupal\Core\Extension\ModuleExtensionList');
    $themeExtensionList = $this->createMock('\Drupal\Core\Extension\ThemeExtensionList');
    $moduleExtensionList
      ->method('getAllInstalledInfo')
      ->willReturn($moduleInfo);
    $themeExtensionList
      ->method('getAllInstalledInfo')
      ->willReturn($themeInfo);
    if (!empty($getPath)) {
      $moduleExtensionList
        ->method('getPath')
        ->will($this->returnValueMap($getPath));
      $themeExtensionList
        ->method('getPath')
        ->will($this->returnValueMap($getPath));
    }
    $themeList = [];
    foreach ($themeInfo as $info) {
      $extension = $this->createMock('\Drupal\Core\Extension\Extension');
      $extension->method('getName')->willReturn($info['name']);
      $extension->method('getType')->willReturn('theme');
      $themeList[] = $extension;
    }
    $valueMap = [];
    foreach (array_keys($themeInfo) as $themeName) {
      $valueMap[] = [$themeList, $themeName, $getBaseThemes[$themeName]];
    }
    $themeExtensionList
      ->method('getList')
      ->willReturn($themeList);
    $themeExtensionList
      ->method('getBaseThemes')
      ->will($this->returnValueMap($valueMap));
    if (!empty($expectedWarnings)) {
      $consecutiveCalls = [];
      foreach ($expectedWarnings as $key => $warning) {
        $consecutiveCalls[$key] = [$warning];
      }
      $this->loggerChannel
        ->expects($this->exactly(count($expectedWarnings)))
        ->method('warning')
        ->withConsecutive(...$consecutiveCalls);
    }

    $this->systemUnderTest = $this->newSystemUnderTest();

    // Use reflection to test a protected method.
    $result = $this->invokeProtectedMethod($this->systemUnderTest, 'findNamespaces', $moduleExtensionList, $themeExtensionList);
    $this->assertEquals($expected, $result, $this->getName());
  }

  /**
   * Provides test data to ::testFindNamespaces().
   *
   * @see testFindNamespaces()
   */
  public function providerTestFindNamespaces(): array {
    return [
      'namespace paths are ordered properly' => [
        'moduleInfo' => [
          'weight1' => [
            'name' => 'Weight 1',
            'type' => 'module',
            'components' => [
              'namespaces' => [
                'components' => ['path1', 'path2'],
                'baseTheme' => ['path3', 'path4'],
              ],
            ],
          ],
          'weight2' => [
            'name' => 'Weight 2',
            'type' => 'module',
            'components' => [
              'namespaces' => [
                'components' => ['path1', 'path2'],
                'baseTheme' => ['path3', 'path4'],
              ],
            ],
          ],
          'components' => [
            'name' => 'Components!',
            'type' => 'module',
            'components' => [
              'namespaces' => [
                'components' => ['path1', 'path2'],
              ],
            ],
          ],
        ],
        'themeInfo' => [
          'activeTheme' => [
            'name' => 'Active theme',
            'type' => 'theme',
            'base theme' => 'baseTheme',
            'components' => [
              'namespaces' => [
                'components' => ['path1', 'path2'],
              ],
            ],
          ],
          'baseTheme' => [
            'name' => 'Base theme',
            'type' => 'theme',
            'components' => [
              'namespaces' => [
                'components' => ['path1', 'path2'],
                'baseTheme' => ['path3', 'path4'],
              ],
            ],
          ],
        ],
        'getPath' => [
          ['components', $this->modulesDir . '/components'],
          ['weight1', $this->modulesDir . '/weight1'],
          ['weight2', $this->modulesDir . '/weight2'],
          ['activeTheme', $this->themesDir . '/activeTheme'],
          ['baseTheme', $this->themesDir . '/baseTheme'],
        ],
        'getBaseThemes' => [
          'activeTheme' => ['baseTheme' => 'Base theme'],
          'baseTheme' => [],
        ],
        'expected' => [
          'activeTheme' => [
            'components' => [
              $this->themesDir . '/activeTheme/path1',
              $this->themesDir . '/activeTheme/path2',
              $this->themesDir . '/baseTheme/path1',
              $this->themesDir . '/baseTheme/path2',
              $this->modulesDir . '/weight2/path1',
              $this->modulesDir . '/weight2/path2',
              $this->modulesDir . '/weight1/path1',
              $this->modulesDir . '/weight1/path2',
              $this->modulesDir . '/components/path1',
              $this->modulesDir . '/components/path2',
            ],
            'baseTheme' => [
              $this->themesDir . '/baseTheme/path3',
              $this->themesDir . '/baseTheme/path4',
              $this->modulesDir . '/weight2/path3',
              $this->modulesDir . '/weight2/path4',
              $this->modulesDir . '/weight1/path3',
              $this->modulesDir . '/weight1/path4',
            ],
          ],
          'baseTheme' => [
            'components' => [
              $this->themesDir . '/baseTheme/path1',
              $this->themesDir . '/baseTheme/path2',
              $this->modulesDir . '/weight2/path1',
              $this->modulesDir . '/weight2/path2',
              $this->modulesDir . '/weight1/path1',
              $this->modulesDir . '/weight1/path2',
              $this->modulesDir . '/components/path1',
              $this->modulesDir . '/components/path2',
            ],
            'baseTheme' => [
              $this->themesDir . '/baseTheme/path3',
              $this->themesDir . '/baseTheme/path4',
              $this->modulesDir . '/weight2/path3',
              $this->modulesDir . '/weight2/path4',
              $this->modulesDir . '/weight1/path3',
              $this->modulesDir . '/weight1/path4',
            ],
          ],
        ],
      ],
      'removes protected namespaces with no components data in info.yml' => [
        'moduleInfo' => [
          'system' => [
            'name' => 'System',
            'type' => 'module',
            'package' => 'Core',
          ],
          'components' => [
            'name' => 'Components!',
            'type' => 'module',
            'components' => [
              'namespaces' => [
                'system' => 'system',
                'classy' => 'classy',
              ],
            ],
          ],
        ],
        'themeInfo' => [
          'classy' => [
            'name' => 'Classy',
            'type' => 'theme',
          ],
          'zen' => [
            'name' => 'Zen',
            'type' => 'theme',
            'components' => [
              'namespaces' => [
                'zen' => 'zen-namespace',
                // All three namespaces should be removed.
                'system' => 'system',
                'components' => 'components-namespace',
                'classy' => 'classy',
              ],
            ],
          ],
        ],
        'getPath' => [
          ['components', $this->modulesDir . '/components'],
          ['zen', $this->themesDir . '/zen'],
        ],
        'getBaseThemes' => [
          'classy' => [],
          'zen' => [],
        ],
        'expected' => [
          'zen' => [
            'zen' => [
              $this->themesDir . '/zen/zen-namespace',
            ],
          ],
          'classy' => [],
        ],
        'expectedWarnings' => [
          'The Components! module attempted to alter the protected Twig namespace, system, owned by the System module. See https://www.drupal.org/node/3190969#s-extending-a-default-twig-namespace to fix this error.',
          'The Components! module attempted to alter the protected Twig namespace, classy, owned by the Classy theme. See https://www.drupal.org/node/3190969#s-extending-a-default-twig-namespace to fix this error.',
          'The Zen theme attempted to alter the protected Twig namespace, system, owned by the System module. See https://www.drupal.org/node/3190969#s-extending-a-default-twig-namespace to fix this error.',
          'The Zen theme attempted to alter the protected Twig namespace, components, owned by the Components! module. See https://www.drupal.org/node/3190969#s-extending-a-default-twig-namespace to fix this error.',
          'The Zen theme attempted to alter the protected Twig namespace, classy, owned by the Classy theme. See https://www.drupal.org/node/3190969#s-extending-a-default-twig-namespace to fix this error.',
        ],
      ],
      'namespace is not protected if default namespace is used' => [
        'moduleInfo' => [
          'system' => [
            'name' => 'System',
            'type' => 'module',
            'package' => 'Core',
          ],
          'components' => [
            'name' => 'Components!',
            'type' => 'module',
            'components' => [
              'namespaces' => [
                'system' => 'system',
                'components' => 'default-namespace',
                'classy' => 'classy',
              ],
            ],
          ],
        ],
        'themeInfo' => [
          'classy' => [
            'name' => 'Classy',
            'type' => 'theme',
          ],
          'zen' => [
            'name' => 'Zen',
            'type' => 'theme',
            'components' => [
              'namespaces' => [
                'zen' => 'zen-namespace',
                'system' => 'system',
                'components' => 'components-namespace',
                'classy' => 'classy',
              ],
            ],
          ],
        ],
        'getPath' => [
          ['components', $this->modulesDir . '/components'],
          ['zen', $this->themesDir . '/zen'],
        ],
        'getBaseThemes' => [
          'classy' => [],
          'zen' => [],
        ],
        'expected' => [
          'zen' => [
            'zen' => [
              $this->themesDir . '/zen/zen-namespace',
            ],
            'components' => [
              $this->themesDir . '/zen/components-namespace',
              $this->modulesDir . '/components/default-namespace',
            ],
          ],
          'classy' => [
            'components' => [
              $this->modulesDir . '/components/default-namespace',
            ],
          ],
        ],
        'expectedWarnings' => [
          'The Components! module attempted to alter the protected Twig namespace, system, owned by the System module. See https://www.drupal.org/node/3190969#s-extending-a-default-twig-namespace to fix this error.',
          'The Components! module attempted to alter the protected Twig namespace, classy, owned by the Classy theme. See https://www.drupal.org/node/3190969#s-extending-a-default-twig-namespace to fix this error.',
          'The Zen theme attempted to alter the protected Twig namespace, system, owned by the System module. See https://www.drupal.org/node/3190969#s-extending-a-default-twig-namespace to fix this error.',
          'The Zen theme attempted to alter the protected Twig namespace, classy, owned by the Classy theme. See https://www.drupal.org/node/3190969#s-extending-a-default-twig-namespace to fix this error.',
        ],
      ],
      'namespace is not protected if manual opt-in .info.yml flag is used' => [
        'moduleInfo' => [
          'system' => [
            'name' => 'System',
            'type' => 'module',
            'package' => 'Core',
          ],
          'components' => [
            'name' => 'Components!',
            'type' => 'module',
            'components' => [
              'allow_default_namespace_reuse' => TRUE,
            ],
          ],
        ],
        'themeInfo' => [
          'classy' => [
            'name' => 'Classy',
            'type' => 'theme',
          ],
          'zen' => [
            'name' => 'Zen',
            'type' => 'theme',
            'components' => [
              'namespaces' => [
                'zen' => 'zen-namespace',
                'system' => 'system',
                'components' => 'components-namespace',
                'classy' => 'classy',
              ],
            ],
          ],
        ],
        'getPath' => [
          ['components', $this->modulesDir . '/components'],
          ['zen', $this->themesDir . '/zen'],
        ],
        'getBaseThemes' => [
          'classy' => [],
          'zen' => [],
        ],
        'expected' => [
          'zen' => [
            'zen' => [
              $this->themesDir . '/zen/zen-namespace',
            ],
            'components' => [
              $this->themesDir . '/zen/components-namespace',
            ],
          ],
          'classy' => [],
        ],
        'expectedWarnings' => [
          'The Zen theme attempted to alter the protected Twig namespace, system, owned by the System module. See https://www.drupal.org/node/3190969#s-extending-a-default-twig-namespace to fix this error.',
          'The Zen theme attempted to alter the protected Twig namespace, classy, owned by the Classy theme. See https://www.drupal.org/node/3190969#s-extending-a-default-twig-namespace to fix this error.',
        ],
      ],
    ];
  }

  /**
   * Tests normalizing components data from extension .info.yml files.
   *
   * @param array $getAllInstalledInfo
   *   The array returned by extensionList::getAllInstalledInfo().
   * @param array $getPath
   *   The PHPUnit returnValueMap array for extensionList::getPath().
   * @param null|array $getBaseThemes
   *   A theme-name-keyed array of return values for
   *   themeExtensionList::getBaseThemes().
   * @param array $expected
   *   The expected result.
   *
   * @throws \ReflectionException
   *
   * @covers ::normalizeExtensionListInfo
   *
   * @dataProvider providerTestNormalizeExtensionListInfo
   */
  public function testNormalizeExtensionListInfo(array $getAllInstalledInfo, array $getPath, ?array $getBaseThemes, array $expected) {
    $this->systemUnderTest = $this->newSystemUnderTest();

    // Mock the method param with the test data.
    $extensionList = $this->createMock(
      is_null($getBaseThemes)
        ? '\Drupal\Core\Extension\ModuleExtensionList'
        : '\Drupal\Core\Extension\ThemeExtensionList'
    );
    $extensionList
      ->method('getAllInstalledInfo')
      ->willReturn($getAllInstalledInfo);
    if (!empty($getPath)) {
      $extensionList
        ->method('getPath')
        ->will($this->returnValueMap($getPath));
    }
    if (!is_null($getBaseThemes)) {
      $themeList = [];
      foreach ($getAllInstalledInfo as $info) {
        $extension = $this->createMock('\Drupal\Core\Extension\Extension');
        $extension->method('getName')->willReturn($info['name']);
        $extension->method('getType')->willReturn('theme');
        $themeList[] = $extension;
      }
      $valueMap = [];
      foreach ($getAllInstalledInfo as $themeName => $info) {
        $valueMap[] = [$themeList, $themeName, $getBaseThemes[$themeName]];
      }
      $extensionList
        ->method('getList')
        ->willReturn($themeList);
      $extensionList
        ->method('getBaseThemes')
        ->will($this->returnValueMap($valueMap));
    }

    // Use reflection to test a protected method.
    $result = $this->invokeProtectedMethod($this->systemUnderTest, 'normalizeExtensionListInfo', $extensionList);
    $this->assertEquals($expected, $result, $this->getName());
  }

  /**
   * Provides test data to ::testNormalizeNamespacePaths().
   *
   * @see testNormalizeNamespacePaths()
   */
  public function providerTestNormalizeExtensionListInfo(): array {
    return [
      'saves extension info, including package' => [
        'getAllInstalledInfo' => [
          'system' => [
            'name' => 'System',
            'type' => 'module',
            'package' => 'Core',
            'no-components' => 'system-value',
          ],
        ],
        'getPath' => [],
        'getBaseThemes' => NULL,
        'expected' => [
          'system' => [
            'extensionInfo' => [
              'name' => 'System',
              'type' => 'module',
              'package' => 'Core',
            ],
            'namespaces' => [],
            'allow_default_namespace_reuse' => FALSE,
          ],
        ],
      ],
      'saves extension info, even if no package' => [
        'getAllInstalledInfo' => [
          'system' => [
            'name' => 'System',
            'type' => 'module',
          ],
        ],
        'getPath' => [],
        'getBaseThemes' => NULL,
        'expected' => [
          'system' => [
            'extensionInfo' => [
              'name' => 'System',
              'type' => 'module',
              'package' => '',
            ],
            'namespaces' => [],
            'allow_default_namespace_reuse' => FALSE,
          ],
        ],
      ],
      'Ignore namespaces using deprecated 1.x API' => [
        'getAllInstalledInfo' => [
          'harriet_tubman' => [
            'name' => 'Harriet Tubman',
            'type' => 'module',
            'component-libraries' => [
              'harriet_tubman' => [
                'paths' => ['deprecated'],
              ],
            ],
          ],
        ],
        'getPath' => [],
        'getBaseThemes' => NULL,
        'expected' => [
          'harriet_tubman' => [
            'extensionInfo' => [
              'name' => 'Harriet Tubman',
              'type' => 'module',
              'package' => '',
            ],
            'namespaces' => [],
            'allow_default_namespace_reuse' => FALSE,
          ],
        ],
      ],
      'namespaces data is normalized' => [
        'getAllInstalledInfo' => [
          'phillis_wheatley' => [
            'name' => 'Phillis Wheatley',
            'type' => 'module',
            'components' => [
              'namespaces' => [
                'wheatley' => ['components'],
                'wheatley_too' => 'templates',
              ],
            ],
          ],
        ],
        'getPath' => [
          ['phillis_wheatley', $this->modulesDir . '/phillis_wheatley'],
        ],
        'getBaseThemes' => NULL,
        'expected' => [
          'phillis_wheatley' => [
            'extensionInfo' => [
              'name' => 'Phillis Wheatley',
              'type' => 'module',
              'package' => '',
            ],
            'namespaces' => [
              'wheatley' => [$this->modulesDir . '/phillis_wheatley/components'],
              'wheatley_too' => [$this->modulesDir . '/phillis_wheatley/templates'],
            ],
            'allow_default_namespace_reuse' => FALSE,
          ],
        ],
      ],
      'Manual opt-in of default namespace reuse' => [
        'getAllInstalledInfo' => [
          'components' => [
            'name' => 'Components!',
            'type' => 'module',
            'components' => [
              'allow_default_namespace_reuse' => TRUE,
            ],
          ],
        ],
        'getPath' => [],
        'getBaseThemes' => NULL,
        'expected' => [
          'components' => [
            'extensionInfo' => [
              'name' => 'Components!',
              'type' => 'module',
              'package' => '',
            ],
            'namespaces' => [],
            'allow_default_namespace_reuse' => TRUE,
          ],
        ],
      ],
      'Theme extensionList adds baseThemes' => [
        'getAllInstalledInfo' => [
          'activeTheme' => [
            'name' => 'Active Theme!',
            'type' => 'theme',
            'base theme' => 'baseTheme',
            'components' => [
              'namespaces' => [
                'activeTheme' => 'active',
                'components' => 'components',
              ],
            ],
          ],
          'baseTheme' => [
            'name' => 'Base Theme',
            'type' => 'theme',
            'base theme' => 'basestTheme',
            'components' => [
              'namespaces' => [
                'components' => 'components',
              ],
            ],
          ],
          'basestTheme' => [
            'name' => 'Basest Theme',
            'type' => 'theme',
            'components' => [
              'namespaces' => [
                'components' => 'components',
              ],
            ],
          ],
        ],
        'getPath' => [
          ['activeTheme', $this->themesDir . '/activeTheme'],
          ['baseTheme', $this->themesDir . '/baseTheme'],
          ['basestTheme', $this->themesDir . '/basestTheme'],
        ],
        'getBaseThemes' => [
          'activeTheme' => [
            'basestTheme' => 'Basest Theme',
            'baseTheme' => 'Base Theme',
          ],
          'baseTheme' => [
            'basestTheme' => 'Basest Theme',
          ],
          'basestTheme' => [],
        ],
        'expected' => [
          'activeTheme' => [
            'extensionInfo' => [
              'name' => 'Active Theme!',
              'type' => 'theme',
              'package' => '',
              'baseThemes' => ['basestTheme', 'baseTheme'],
            ],
            'namespaces' => [
              'activeTheme' => [$this->themesDir . '/activeTheme/active'],
              'components' => [$this->themesDir . '/activeTheme/components'],
            ],
            'allow_default_namespace_reuse' => FALSE,
          ],
          'baseTheme' => [
            'extensionInfo' => [
              'name' => 'Base Theme',
              'type' => 'theme',
              'package' => '',
              'baseThemes' => ['basestTheme'],
            ],
            'namespaces' => [
              'components' => [$this->themesDir . '/baseTheme/components'],
            ],
            'allow_default_namespace_reuse' => FALSE,
          ],
          'basestTheme' => [
            'extensionInfo' => [
              'name' => 'Basest Theme',
              'type' => 'theme',
              'package' => '',
              'baseThemes' => [],
            ],
            'namespaces' => [
              'components' => [$this->themesDir . '/basestTheme/components'],
            ],
            'allow_default_namespace_reuse' => FALSE,
          ],
        ],
      ],
      'Handles invalid base themes' => [
        'getAllInstalledInfo' => [
          'activeTheme' => [
            'name' => 'Active Theme!',
            'type' => 'theme',
            'base theme' => 'baseTheme',
            'components' => [
              'namespaces' => [
                'activeTheme' => 'active',
                'components' => 'components',
              ],
            ],
          ],
          'baseTheme' => [
            'name' => 'Base Theme',
            'type' => 'theme',
            'base theme' => 'basestTheme',
            'components' => [
              'namespaces' => [
                'components' => 'components',
              ],
            ],
          ],
        ],
        'getPath' => [
          ['activeTheme', $this->themesDir . '/activeTheme'],
          ['baseTheme', $this->themesDir . '/baseTheme'],
        ],
        'getBaseThemes' => [
          'activeTheme' => [
            'basestTheme' => NULL,
            'baseTheme' => 'Base Theme',
          ],
          'baseTheme' => [
            'basestTheme' => NULL,
          ],
        ],
        'expected' => [
          'activeTheme' => [
            'extensionInfo' => [
              'name' => 'Active Theme!',
              'type' => 'theme',
              'package' => '',
              'baseThemes' => ['baseTheme'],
            ],
            'namespaces' => [
              'activeTheme' => [$this->themesDir . '/activeTheme/active'],
              'components' => [$this->themesDir . '/activeTheme/components'],
            ],
            'allow_default_namespace_reuse' => FALSE,
          ],
          'baseTheme' => [
            'extensionInfo' => [
              'name' => 'Base Theme',
              'type' => 'theme',
              'package' => '',
              'baseThemes' => [],
            ],
            'namespaces' => [
              'components' => [$this->themesDir . '/baseTheme/components'],
            ],
            'allow_default_namespace_reuse' => FALSE,
          ],
        ],
      ],
    ];
  }

  /**
   * Tests normalizing namespace paths.
   *
   * @param string|string[] $paths
   *   The list of namespaces.
   * @param string $extensionPath
   *   The path to the extension defining the namespaces.
   * @param array $expected
   *   The expected result.
   *
   * @dataProvider providerTestNormalizeNamespacePaths
   */
  public function testNormalizeNamespacePaths($paths, string $extensionPath, array $expected): void {
    $result = ComponentsRegistry::normalizeNamespacePaths($paths, $extensionPath);
    $this->assertEquals($expected, $result, $this->getName());
  }

  /**
   * Provides test data to ::testNormalizeNamespacePaths().
   *
   * @see testNormalizeNamespacePaths()
   */
  public function providerTestNormalizeNamespacePaths(): array {
    return [
      'namespaces path is string' => [
        'paths' => 'myNamespacePath',
        'extensionPath' => $this->themesDir,
        'expected' => [$this->themesDir . '/myNamespacePath'],
      ],
      'namespaces path is array' => [
        'paths' => ['myNamespacePath'],
        'extensionPath' => $this->themesDir,
        'expected' => [$this->themesDir . '/myNamespacePath'],
      ],
      'namespace path is relative to Drupal root'  => [
        'paths' => [
          'templates',
          '/libraries/chapman/components',
        ],
        'extensionPath' => $this->themesDir . '/chapman',
        'expected' => [
          $this->themesDir . '/chapman/templates',
          $this->rootDir . '/libraries/chapman/components',
        ],
      ],
    ];
  }

  /**
   * Tests registering protected namespaces.
   *
   * @param array $extensionInfo
   *   The array of extensions.
   * @param array $expected
   *   The expected value of protectedNamespaces.
   *
   * @throws \ReflectionException
   *
   * @covers ::findProtectedNamespaces
   *
   * @dataProvider providerTestFindProtectedNamespaces
   */
  public function testFindProtectedNamespaces(array $extensionInfo, array $expected): void {
    // Test that hook_protected_twig_namespaces_alter() is called for modules.
    $moduleHandler = $this->createMock('\Drupal\Core\Extension\ModuleHandlerInterface');
    $moduleHandler
      ->method('alter')
      ->withConsecutive(
        ['protected_twig_namespaces', $expected, NULL, NULL],
      );

    // Test that hook_protected_twig_namespaces_alter() is called for themes.
    $themeManager = $this->createMock('\Drupal\Core\Theme\ThemeManagerInterface');
    $themeManager
      ->method('alter')
      ->withConsecutive(
        ['protected_twig_namespaces', $expected, NULL, NULL],
      );

    // Mock the system under test.
    $this->systemUnderTest = $this->newSystemUnderTest(
      NULL,
      NULL,
      $moduleHandler,
      $themeManager
    );

    $result = $this->invokeProtectedMethod($this->systemUnderTest, 'findProtectedNamespaces', $extensionInfo);
    $this->assertEquals($expected, $result, $this->getName());
  }

  /**
   * Provides test data to ::testFindProtectedNamespaces().
   *
   * @see testFindProtectedNamespaces()
   */
  public function providerTestFindProtectedNamespaces(): array {
    return [
      'Manual opt-in' => [
        'extensionInfo' => [
          'edna_lewis' => [
            'extensionInfo' => [
              'name' => 'Edna Lewis',
              'type' => 'module',
              'package' => '',
            ],
            'namespaces' => [],
            'allow_default_namespace_reuse' => TRUE,
          ],
        ],
        'expected' => [],
      ],
      'Default namespace is defined' => [
        'extensionInfo' => [
          'edna_lewis' => [
            'extensionInfo' => [
              'name' => 'Edna Lewis',
              'type' => 'module',
              'package' => '',
            ],
            'namespaces' => [
              'edna_lewis' => [
                $this->modulesDir . '/edna_lewis/components',
              ],
            ],
            'allow_default_namespace_reuse' => FALSE,
          ],
        ],
        'expected' => [],
      ],
      'Namespace is protected' => [
        'extensionInfo' => [
          'edna_lewis' => [
            'extensionInfo' => [
              'name' => 'Edna Lewis',
              'type' => 'module',
              'package' => '',
            ],
            'namespaces' => [],
            'allow_default_namespace_reuse' => FALSE,
          ],
        ],
        'expected' => [
          'edna_lewis' => [
            'name' => 'Edna Lewis',
            'type' => 'module',
            'package' => '',
          ],
        ],
      ],
    ];
  }

}
