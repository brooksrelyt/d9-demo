<?php

namespace Drupal\components\Template;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Loads info about components defined in themes or modules.
 */
class ComponentsRegistry {

  use LoggerChannelTrait;

  /**
   * Cache of namespaces for each theme.
   *
   * @var array
   */
  protected $namespaces = [];

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The theme extension list service.
   *
   * @var \Drupal\Core\Extension\ThemeExtensionList
   */
  protected $themeExtensionList;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Stores whether the registry was already initialized.
   *
   * @var bool
   */
  protected $initialized = FALSE;

  /**
   * Constructs a new ComponentsRegistry object.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list service.
   * @param \Drupal\Core\Extension\ThemeExtensionList $themeExtensionList
   *   The theme extension list service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache backend for storing the components registry info.
   */
  public function __construct(
    ModuleExtensionList $moduleExtensionList,
    ThemeExtensionList $themeExtensionList,
    ModuleHandlerInterface $moduleHandler,
    ThemeManagerInterface $themeManager,
    CacheBackendInterface $cache
  ) {
    $this->moduleExtensionList = $moduleExtensionList;
    $this->themeExtensionList = $themeExtensionList;
    $this->moduleHandler = $moduleHandler;
    $this->themeManager = $themeManager;
    $this->cache = $cache;
  }

  /**
   * Initializes the registry and loads the theme namespaces.
   */
  protected function init(): void {
    $this->initialized = TRUE;

    if ($cached = $this->cache->get('components:namespaces')) {
      $this->namespaces = $cached->data;
      return;
    }

    $this->namespaces = $this->findNamespaces($this->moduleExtensionList, $this->themeExtensionList);
    $this->cache->set(
      'components:namespaces',
      $this->namespaces,
      Cache::PERMANENT,
      ['theme_registry']
    );
  }

  /**
   * Gets the name of the active theme.
   *
   * @return string
   *   The machine name of the active theme.
   */
  public function getActiveThemeName(): string {
    return $this->themeManager->getActiveTheme()->getName();
  }

  /**
   * Get namespaces for the active theme.
   *
   * @return array
   *   The array of namespaces.
   */
  public function getNamespaces(): array {
    if (!$this->initialized) {
      $this->init();
    }

    $activeTheme = $this->getActiveThemeName();
    if ($cached = $this->cache->get('components:namespaces:' . $activeTheme)) {
      $this->namespaces[$activeTheme] = $cached->data;
      return $this->namespaces[$activeTheme];
    }

    $namespaces = isset($this->namespaces[$activeTheme]) ? $this->namespaces[$activeTheme] : [];

    // Run hook_components_namespaces_alter().
    $this->moduleHandler->alter('components_namespaces', $namespaces, $activeTheme);
    $this->themeManager->alter('components_namespaces', $namespaces, $activeTheme);

    $this->namespaces[$activeTheme] = $namespaces;
    $this->cache->set(
      'components:namespaces:' . $activeTheme,
      $this->namespaces[$activeTheme],
      Cache::PERMANENT,
      ['theme_registry']
    );

    return $namespaces;
  }

  /**
   * Finds namespaces for all installed themes.
   *
   * Templates in namespaces will be loaded from paths in this priority:
   * 1. active theme
   * 2. active theme's base themes
   * 3. modules:
   *    a. non-default namespaces
   *    b. default namespaces.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list service.
   * @param \Drupal\Core\Extension\ThemeExtensionList $themeExtensionList
   *   The theme extension list service.
   *
   * @return array
   *   An array of namespaces lists, keyed for each installed theme.
   */
  protected function findNamespaces(ModuleExtensionList $moduleExtensionList, ThemeExtensionList $themeExtensionList): array {
    $moduleInfo = $this->normalizeExtensionListInfo($moduleExtensionList);
    $themeInfo = $this->normalizeExtensionListInfo($themeExtensionList);

    $protectedNamespaces = $this->findProtectedNamespaces($moduleInfo + $themeInfo);

    // Collect module namespaces since they are valid for any active theme.
    $moduleNamespaces = [];

    // Find default namespaces for modules.
    foreach ($moduleInfo as $defaultName => &$info) {
      if (isset($info['namespaces'][$defaultName])) {
        $moduleNamespaces[$defaultName] = $info['namespaces'][$defaultName];
        unset($info['namespaces'][$defaultName]);
      }
    }

    // Find other namespaces defined by modules.
    foreach ($moduleInfo as $moduleName => &$info) {
      foreach ($info['namespaces'] as $namespace => $paths) {
        // Skip protected namespaces and log a warning.
        if (isset($protectedNamespaces[$namespace])) {
          $extensionInfo = $protectedNamespaces[$namespace];
          $this->logWarning(sprintf('The %s module attempted to alter the protected Twig namespace, %s, owned by the %s %s. See https://www.drupal.org/node/3190969#s-extending-a-default-twig-namespace to fix this error.', $info['extensionInfo']['name'], $namespace, $extensionInfo['name'], $extensionInfo['type']));
        }
        else {
          $moduleNamespaces[$namespace] = !isset($moduleNamespaces[$namespace])
            ? $paths
            : array_merge($paths, $moduleNamespaces[$namespace]);
        }
      }
    }

    // Remove protected namespaces from each theme's namespaces and log a
    // warning.
    foreach ($themeInfo as $themeName => &$info) {
      foreach (array_keys($info['namespaces']) as $namespace) {
        if (isset($protectedNamespaces[$namespace])) {
          unset($info['namespaces'][$namespace]);
          $extensionInfo = $protectedNamespaces[$namespace];
          $this->logWarning(sprintf('The %s theme attempted to alter the protected Twig namespace, %s, owned by the %s %s. See https://www.drupal.org/node/3190969#s-extending-a-default-twig-namespace to fix this error.', $info['extensionInfo']['name'], $namespace, $extensionInfo['name'], $extensionInfo['type']));
        }
      }
    }

    // Build the full list of namespaces for each theme.
    $namespaces = [];
    foreach (array_keys($themeInfo) as $activeTheme) {
      $namespaces[$activeTheme] = $moduleNamespaces;
      foreach (array_merge($themeInfo[$activeTheme]['extensionInfo']['baseThemes'], [$activeTheme]) as $themeName) {
        foreach ($themeInfo[$themeName]['namespaces'] as $namespace => $paths) {
          $namespaces[$activeTheme][$namespace] = !isset($namespaces[$activeTheme][$namespace])
            ? $paths
            : array_merge($paths, $namespaces[$activeTheme][$namespace]);
        }
      }
    }

    return $namespaces;
  }

  /**
   * Gets info from the given extension list and normalizes components data.
   *
   * @param \Drupal\Core\Extension\ExtensionList $extensionList
   *   The extension list to search.
   *
   * @return array
   *   Components-related info for all extensions in the extension list.
   */
  protected function normalizeExtensionListInfo(ExtensionList $extensionList): array {
    $data = [];

    $themeExtensions = method_exists($extensionList, 'getBaseThemes') ? $extensionList->getList() : [];
    foreach ($extensionList->getAllInstalledInfo() as $name => $extensionInfo) {
      $data[$name] = [
        // Save information about the extension.
        'extensionInfo' => [
          'name' => $extensionInfo['name'],
          'type' => $extensionInfo['type'],
          'package' => isset($extensionInfo['package']) ? $extensionInfo['package'] : '',
        ],
      ];
      if (method_exists($extensionList, 'getBaseThemes')) {
        $data[$name]['extensionInfo']['baseThemes'] = [];
        foreach ($extensionList->getBaseThemes($themeExtensions, $name) as $baseTheme => $baseThemeName) {
          // If NULL is given as the name of any base theme, then Drupal
          // encountered an error trying to find the base themes. If this
          // happens for an active theme, Drupal will throw a fatal error. But
          // this may happen for a non-active, installed theme and the
          // components module should simply ignore the broken base theme since
          // the error won't affect the active theme.
          if (!is_null($baseThemeName)) {
            $data[$name]['extensionInfo']['baseThemes'][] = $baseTheme;
          }
        }
      }

      $info = isset($extensionInfo['components']) && is_array($extensionInfo['components'])
        ? $extensionInfo['components']
        : [];

      // Normalize namespace data.
      $data[$name]['namespaces'] = [];
      if (isset($info['namespaces'])) {
        $extensionPath = $extensionList->getPath($name);
        foreach ($info['namespaces'] as $namespace => $paths) {
          $data[$name]['namespaces'][$namespace] = self::normalizeNamespacePaths(
            $paths,
            $extensionPath
          );
        }
      }

      // Find default namespace flag.
      $data[$name]['allow_default_namespace_reuse'] = isset($info['allow_default_namespace_reuse']);
    }

    return $data;
  }

  /**
   * Normalizes namespaces using the given path to the extension.
   *
   * If a namespace's path starts with a "/", the path is relative to the root
   * Drupal installation path (i.e. the directory that contains Drupal's "core"
   * directory.) Otherwise, the path is relative to the $extensionPath.
   *
   * @param string|string[] $paths
   *   The list of namespaces as a single string containing the path or a
   *   numerically-indexed array of paths.
   * @param string $extensionPath
   *   The path to the extension defining the namespace.
   *
   * @return array
   *   The normalized list of namespace paths, an array of absolute paths on the
   *   web server's file system.
   */
  public static function normalizeNamespacePaths($paths, string $extensionPath = ''): array {
    // Allow paths to be an array or a string.
    if (!is_array($paths)) {
      $paths = [$paths];
    }

    // Add the full path to the namespace paths.
    foreach ($paths as $key => $path) {
      // Determine if the given path is relative to the Drupal root or to
      // the extension.
      $parentPath = ($path[0] === '/')
        ? \Drupal::root()
        // Append "/" since $path does not start with "/".
        : $extensionPath . '/';
      $paths[$key] = $parentPath . $path;
    }

    return $paths;
  }

  /**
   * Finds protected namespaces.
   *
   * @param array $extensionInfo
   *   The array of extensions in the format returned by
   *   normalizeExtensionListInfo().
   *
   * @return array
   *   The array of protected namespaces.
   */
  protected function findProtectedNamespaces(array $extensionInfo): array {
    $protectedNamespaces = [];

    foreach ($extensionInfo as $defaultName => $info) {
      // The extension opted-in to having its default namespace be reusable.
      if ($info['allow_default_namespace_reuse']) {
        continue;
      }

      // The extension is defining its default namespace; other extensions are
      // allowed to add paths to it.
      if (!empty($info['namespaces'][$defaultName])) {
        continue;
      }

      // All other default namespaces are protected.
      $protectedNamespaces[$defaultName] = [
        'name' => $info['extensionInfo']['name'],
        'type' => $info['extensionInfo']['type'],
        'package' => isset($info['extensionInfo']['package']) ? $info['extensionInfo']['package'] : '',
      ];
    }

    // Run hook_protected_twig_namespaces_alter().
    $this->moduleHandler->alter('protected_twig_namespaces', $protectedNamespaces);
    $this->themeManager->alter('protected_twig_namespaces', $protectedNamespaces);

    return $protectedNamespaces;
  }

  /**
   * Logs exceptional occurrences that are not errors.
   *
   * Example: Use of deprecated APIs, poor use of an API, undesirable things
   * that are not necessarily wrong.
   *
   * @param string $message
   *   The warning to log.
   * @param mixed[] $context
   *   Any additional context to pass to the logger.
   *
   * @internal
   */
  protected function logWarning(string $message, array $context = []): void {
    $this->getLogger('components')->warning($message, $context);
  }

}
