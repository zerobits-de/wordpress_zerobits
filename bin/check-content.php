#!/usr/bin/env php
<?php
/**
 * Checks the repository content of the theme (CI and `make lint`).
 *
 * Usage: php bin/check-content.php <theme-dir>
 *
 *  - content/site.json and content/{lang}.json are valid JSON
 *  - every language file has exactly the same keys as the default language,
 *    lists have the same length, and no text is empty
 *  - every item id in site.json has texts in every language
 *  - every image referenced in site.json exists in assets/
 *  - every legal page exists for every language, and the slugs are unique
 *  - every feature and social icon exists, also in the blocks of a service
 *  - every technology a service or a project lists ("tools") is an id of the
 *    list "tools" in site.json
 *  - the slugs of the services and projects (and of the former services,
 *    portfolio and Odoo pages, which redirect) are valid and unique
 *
 * Exit code 1 on any problem.
 */

declare(strict_types=1);

$theme = rtrim($argv[1] ?? '', '/');

if ('' === $theme || ! is_dir($theme . '/content')) {
    fwrite(STDERR, "Usage: php bin/check-content.php <theme-dir>\n");
    exit(2);
}

$errors = [];

function load(string $file, array &$errors): array
{
    if (! is_file($file)) {
        $errors[] = "missing $file";
        return [];
    }

    try {
        $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        $errors[] = "$file: invalid JSON ({$e->getMessage()})";
        return [];
    }

    if (! is_array($data)) {
        $errors[] = "$file: must contain an object";
        return [];
    }

    return $data;
}

/** Compare the structure of $other against $reference. */
function compare(mixed $reference, mixed $other, string $path, string $lang, array &$errors): void
{
    if (is_array($reference) && array_is_list($reference)) {
        if (! is_array($other) || ! array_is_list($other)) {
            $errors[] = "$lang: $path must be a list";
            return;
        }
        if (count($reference) !== count($other)) {
            $errors[] = "$lang: $path has " . count($other) . ' entries, expected ' . count($reference);
        }
        foreach ($other as $i => $value) {
            compare($reference[$i] ?? $value, $value, $path . "[$i]", $lang, $errors);
        }
        return;
    }

    if (is_array($reference)) {
        if (! is_array($other) || array_is_list($other) && [] !== $other) {
            $errors[] = "$lang: $path must be an object";
            return;
        }
        foreach (array_diff(array_keys($reference), array_keys($other)) as $key) {
            $errors[] = "$lang: missing key " . ltrim("$path.$key", '.');
        }
        foreach (array_diff(array_keys($other), array_keys($reference)) as $key) {
            $errors[] = "$lang: unexpected key " . ltrim("$path.$key", '.');
        }
        foreach ($other as $key => $value) {
            if (array_key_exists($key, $reference)) {
                compare($reference[$key], $value, ltrim("$path.$key", '.'), $lang, $errors);
            }
        }
        return;
    }

    if (! is_string($other)) {
        $errors[] = "$lang: $path must be a text";
    } elseif ('' === trim($other)) {
        $errors[] = "$lang: $path is empty";
    }
}

$site      = load("$theme/content/site.json", $errors);
$languages = $site['languages'] ?? [];
$default   = $site['default_language'] ?? '';

if (! is_array($languages) || [] === $languages) {
    $errors[] = 'site.json: "languages" must list at least one language';
    $languages = [];
}
if (! in_array($default, $languages, true)) {
    $errors[] = 'site.json: "default_language" must be one of "languages"';
}

$strings = [];
foreach ($languages as $lang) {
    if (! is_string($lang) || 1 !== preg_match('/^[a-z]{2}$/', $lang)) {
        $errors[] = 'site.json: language codes are two lower-case letters';
        continue;
    }
    $strings[$lang] = load("$theme/content/$lang.json", $errors);
}

// Same structure as the default language.
if (isset($strings[$default])) {
    foreach ($strings as $lang => $data) {
        compare($strings[$default], $data, '', $lang, $errors);
    }
}

// Item ids of site.json need texts: section => list in site.json.
$lists = [
    'services'  => $site['services'] ?? [],
    'tools'     => $site['tools'] ?? [],
    'portfolio' => $site['portfolio'] ?? [],
    'pricing'   => $site['pricing'] ?? [],
];
foreach ($lists as $section => $items) {
    foreach ((array) $items as $i => $item) {
        $id = is_array($item) ? ($item['id'] ?? '') : '';
        if ('' === $id) {
            $errors[] = "site.json: {$section}[{$i}] has no id";
            continue;
        }
        foreach ($strings as $lang => $data) {
            if (! isset($data[$section]['items'][$id])) {
                $errors[] = "$lang: missing texts $section.items.$id (listed in site.json)";
            }
        }
    }
}

// Features of the services and of their blocks: texts in every language, an existing icon.
$check_features = static function (array $owner, string $where, array $path) use ($theme, $strings, &$errors): void {
    foreach ((array) ($owner['features'] ?? []) as $j => $feature) {
        $key  = is_array($feature) ? ($feature['id'] ?? '') : '';
        $icon = is_array($feature) ? ($feature['icon'] ?? '') : '';
        if ('' === $key) {
            $errors[] = "site.json: $where.features[$j] has no id";
            continue;
        }
        if (! is_file("$theme/assets/images/icons/$icon.svg")) {
            $errors[] = "site.json: $where.features[$j] icon \"$icon\" is not in assets/images/icons/";
        }
        foreach ($strings as $lang => $data) {
            $texts = $data;
            foreach ([...$path, 'features', $key] as $segment) {
                $texts = is_array($texts) ? ($texts[$segment] ?? null) : null;
            }
            if (! isset($texts['title'])) {
                $errors[] = "$lang: missing texts " . implode('.', [...$path, 'features', $key]) . ' (listed in site.json)';
            }
        }
    }
};
// Technologies of a service or project: ids of the list "tools".
$tool_ids    = array_column(array_filter((array) ($site['tools'] ?? []), 'is_array'), 'id');
$check_tools = static function (array $owner, string $where) use ($tool_ids, &$errors): void {
    if (! array_key_exists('tools', $owner)) {
        return;
    }
    if (! is_array($owner['tools']) || ! array_is_list($owner['tools'])) {
        $errors[] = "site.json: $where.tools must be a list of tool ids";
        return;
    }
    foreach ($owner['tools'] as $tool) {
        if (! is_string($tool) || ! in_array($tool, $tool_ids, true)) {
            $errors[] = "site.json: $where.tools: \"" . (is_string($tool) ? $tool : '?') . '" is not in the list "tools"';
        }
    }
};
foreach ((array) ($site['portfolio'] ?? []) as $i => $project) {
    if (is_array($project)) {
        $check_tools($project, "portfolio[$i]");
    }
}
$odoo_services = 0;
foreach ((array) ($site['services'] ?? []) as $i => $service) {
    if (! is_array($service) || '' === ($service['id'] ?? '')) {
        continue;
    }
    $id = $service['id'];
    $check_features($service, "services[$i]", ['services', 'items', $id]);
    $check_tools($service, "services[$i]");
    $odoo_services += true === ($service['odoo'] ?? false) ? 1 : 0;
    if (! is_string($service['card_icon'] ?? null)) {
        $errors[] = "site.json: services[$i] has no card_icon";
    }
    foreach ((array) ($service['blocks'] ?? []) as $j => $block) {
        $key = is_array($block) ? ($block['id'] ?? '') : '';
        if ('' === $key) {
            $errors[] = "site.json: services[$i].blocks[$j] has no id";
            continue;
        }
        foreach ($strings as $lang => $data) {
            if (! isset($data['services']['items'][$id]['blocks'][$key]['title'])) {
                $errors[] = "$lang: missing texts services.items.$id.blocks.$key (listed in site.json)";
            }
        }
        $check_features($block, "services[$i].blocks[$j]", ['services', 'items', $id, 'blocks', $key]);
    }
}
if (1 !== $odoo_services) {
    $errors[] = 'site.json: exactly one service needs "odoo": true (its page shows the Odoo partner section)';
}
foreach ((array) ($site['social'] ?? []) as $i => $entry) {
    $id = is_array($entry) ? ($entry['id'] ?? '') : '';
    if (! is_file("$theme/assets/images/icons/" . ($entry['icon'] ?? '') . '.svg')) {
        $errors[] = "site.json: social[$i] icon is not in assets/images/icons/";
    }
    foreach ($strings as $lang => $data) {
        if (! isset($data['footer']['networks'][$id])) {
            $errors[] = "$lang: missing text footer.networks.$id (listed in site.json)";
        }
    }
}

// Images referenced anywhere in site.json.
array_walk_recursive($site, static function ($value, $key) use ($theme, &$errors): void {
    $is_image = in_array($key, ['image', 'logo', 'card_icon', 'background'], true) || 1 === preg_match('#^images/#', (string) $value);
    if ($is_image && is_string($value) && ! is_file("$theme/assets/$value")) {
        $errors[] = "site.json: image assets/$value does not exist";
    }
});

// /{services.slug}/{slug of a service}/ and /{portfolio.slug}/{slug of a project}/ per language;
// /{services.slug}/, /{portfolio.slug}/ and /{odoo.slug}/ redirect.
$bases = [];
foreach ($strings as $lang => $data) {
    foreach (['services', 'portfolio', 'odoo'] as $page) {
        $slug = $data[$page]['slug'] ?? '';
        $full = ($lang === $default ? '' : "$lang/") . $slug;
        if (1 !== preg_match('/^[a-z0-9-]+$/', (string) $slug)) {
            $errors[] = "$lang: $page.slug must be lower-case letters, digits and dashes";
        } elseif (isset($bases[$full]) || in_array($slug, ['blog', ...$languages], true)) {
            $errors[] = "$lang: $page.slug \"$slug\" collides with another URL";
        }
        $bases[$full] = true;
    }

    $service_slugs = [];
    foreach ((array) ($site['services'] ?? []) as $service) {
        $id   = is_array($service) ? ($service['id'] ?? '') : '';
        $slug = $data['services']['items'][$id]['slug'] ?? '';
        if (1 !== preg_match('/^[a-z0-9-]+$/', (string) $slug)) {
            $errors[] = "$lang: services.items.$id.slug must be lower-case letters, digits and dashes";
        } elseif (isset($service_slugs[$slug])) {
            $errors[] = "$lang: services.items.$id.slug \"$slug\" is used twice";
        }
        $service_slugs[$slug] = true;
    }

    $project_slugs = [];
    foreach ((array) ($site['portfolio'] ?? []) as $project) {
        $id   = is_array($project) ? ($project['id'] ?? '') : '';
        $slug = $data['portfolio']['items'][$id]['slug'] ?? '';
        if (1 !== preg_match('/^[a-z0-9-]+$/', (string) $slug)) {
            $errors[] = "$lang: portfolio.items.$id.slug must be lower-case letters, digits and dashes";
        } elseif (isset($project_slugs[$slug])) {
            $errors[] = "$lang: portfolio.items.$id.slug \"$slug\" is used twice";
        }
        $project_slugs[$slug] = true;
    }
}

// Legal pages.
$slugs = $bases;
foreach ((array) ($site['legal'] ?? []) as $key) {
    foreach ($strings as $lang => $data) {
        if (! is_file("$theme/content/legal/$lang/$key.html")) {
            $errors[] = "missing content/legal/$lang/$key.html";
        }
        $slug = $data['legal'][$key]['slug'] ?? '';
        if (1 !== preg_match('/^[a-z0-9-]+$/', (string) $slug)) {
            $errors[] = "$lang: legal.$key.slug must be lower-case letters, digits and dashes";
        }
        $full = ($lang === $default ? '' : "$lang/") . $slug;
        if (isset($slugs[$full]) || in_array($slug, ['blog', ...$languages], true)) {
            $errors[] = "$lang: legal.$key.slug \"$slug\" collides with another URL";
        }
        $slugs[$full] = true;
    }
}

if ([] !== $errors) {
    fwrite(STDERR, implode("\n", array_map(static fn ($e) => "FAIL  $e", $errors)) . "\n");
    fwrite(STDERR, sprintf("\n%d content problem(s).\n", count($errors)));
    exit(1);
}

printf("Content OK: %d language(s) (%s), structures match.\n", count($strings), implode(', ', array_keys($strings)));
