<?php

/**
 * @file
 * Hooks related to the Components module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the list of namespaces for a particular theme.
 *
 * @param array $namespaces
 *   The array of Twig namespaces where the key is the machine name of the
 *   namespace and the value is an array of absolute directory paths.
 * @param string $theme
 *   The name of the theme that the namespaces are defined for.
 *
 * @see https://www.drupal.org/node/3190969
 */
function hook_components_namespaces_alter(array &$namespaces, string $theme) {
  // Add a new namespace.
  $namespaces['new_namespace'] = [
    // Paths must be absolute directory paths.
    '/usr/local/var/web/new-components',
  ];

  // Optionally, you can use a helper function to specify paths relative to the
  // Drupal root. Paths must start with a "/" or the Drupal root path will not
  // be prepended.
  $namespaces['vendor_namespace'] = \Drupal\components\Template\ComponentsRegistry::normalizeNamespacePaths(
    [
      '/vender/universe/fermions',
    ]
  );
}

/**
 * Alter the list of protected Twig namespaces.
 *
 * @param array $protectedNamespaces
 *   The array of protected Twig namespaces, keyed on the machine name of the
 *   namespace. Within each array entry, the following pieces of data are
 *   available:
 *   - name: While the array key is the default Twig namespace (which is also
 *     the machine name of the module/theme that owns it), this "name" is the
 *     friendly name of the module/theme used in Drupal's admin lists.
 *   - type: The extension type: module, theme, or profile.
 *   - package: The package name the module is listed under or an empty string.
 *
 * @see https://www.drupal.org/node/3190969
 */
function hook_protected_twig_namespaces_alter(array &$protectedNamespaces) {
  // Allow the "block" Twig namespace to be altered.
  unset($protectedNamespaces['block']);

  // Allow alteration of any Twig namespace for a "Core" module.
  foreach ($protectedNamespaces as $name => $info) {
    if ($info['package'] === 'Core') {
      unset($protectedNamespaces[$name]);
    }
  }

  // Allow alteration of any Twig namespace for any theme.
  foreach ($protectedNamespaces as $name => $info) {
    if ($info['type'] === 'theme') {
      unset($protectedNamespaces[$name]);
    }
  }

  // Allow alteration of all Twig namespaces.
  $protectedNamespaces = [];
}

/**
 * @} End of "addtogroup hooks".
 */
