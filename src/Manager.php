<?php

namespace Barryvdh\TranslationManager;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Barryvdh\TranslationManager\Models\Translation;

class Manager
{
    /**
     * The Laravel Application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * The filesystem.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The event dispatcher.
     *
     * @var \Illuminate\Contracts\Events\Dispatcher
     */
    protected $events;

    /**
     * The configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Manager constructor.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @param \Illuminate\Filesystem\Filesystem            $files
     * @param \Illuminate\Contracts\Events\Dispatcher      $events
     */
    public function __construct(Application $app, Filesystem $files, Dispatcher $events)
    {
        $this->app    = $app;
        $this->files  = $files;
        $this->events = $events;
        $this->config = $app['config']['translation-manager'];
    }

    /**
     * Add missing key to the database.
     *
     * @param string $namespace
     * @param string $group
     * @param string $key
     */
    public function missingKey($namespace, $group, $key)
    {
        if (!in_array($group, $this->config['exclude_groups'])) {
            Translation::firstOrCreate([
                'locale' => $this->app['config']['app.locale'],
                'group'  => $group,
                'key'    => $key,
            ]);
        }
    }

    /**
     * Import translations.
     *
     * @param bool $replace
     *
     * @return int
     */
    public function importTranslations($replace = false)
    {
        $counter = 0;
        foreach ($this->files->directories($this->app->langPath()) as $langPath) {
            $locale = basename($langPath);

            foreach ($this->files->allfiles($langPath) as $file) {

                $info  = pathinfo($file);
                $group = $info['filename'];

                if (in_array($group, $this->config['exclude_groups'])) {
                    continue;
                }

                $subLangPath = str_replace($langPath . "\\", "", $info['dirname']);
                if ($subLangPath != $langPath) {
                    $group = $subLangPath . "/" . $group;
                }

                $translations = \Lang::getLoader()->load($locale, $group);
                if ($translations && is_array($translations)) {
                    foreach (array_dot($translations) as $key => $value) {
                        // process only string values
                        if (is_array($value)) {
                            continue;
                        }
                        $value       = (string)$value;
                        $translation = Translation::firstOrNew([
                            'locale' => $locale,
                            'group'  => $group,
                            'key'    => $key,
                        ]);

                        // Check if the database is different then the files
                        $newStatus = $translation->value === $value ? Translation::STATUS_SAVED : Translation::STATUS_CHANGED;
                        if ($newStatus !== (int)$translation->status) {
                            $translation->status = $newStatus;
                        }

                        // Only replace when empty, or explicitly told so
                        if ($replace || !$translation->value) {
                            $translation->value = $value;
                        }

                        $translation->save();

                        $counter++;
                    }
                }
            }
        }

        return $counter;
    }

    /**
     * Find translation files in a path.
     *
     * @param string|null $path
     *
     * @return int
     */
    public function findTranslations($path = null)
    {
        $path      = $path ?: base_path();
        $keys      = [];
        $functions = [
            'trans',
            'trans_choice',
            'Lang::get',
            'Lang::choice',
            'Lang::trans',
            'Lang::transChoice',
            '@lang',
            '@choice',
        ];
        $pattern   =                                // See http://regexr.com/392hu
            "[^\w|>]" .                             // Must not have an alphanum or _ or > before real method
            "(" . implode('|', $functions) . ")" .  // Must start with one of the functions
            "\(" .                                  // Match opening parenthese
            "[\'\"]" .                              // Match " or '
            "(" .                                   // Start a new group to match:
            "[a-zA-Z0-9_-]+" .                      // Must start with group
            "([.][^\1)]+)+" .                       // Be followed by one or more items/keys
            ")" .                                   // Close group
            "[\'\"]" .                              // Closing quote
            "[\),]";                                // Close parentheses or new parameter

        // Find all PHP + Twig files in the app folder, except for storage
        $finder = new Finder();
        $finder->in($path)->exclude('storage')->name('*.php')->name('*.twig')->files();

        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($finder as $file) {
            // Search the current file for the pattern
            if (preg_match_all("/$pattern/siU", $file->getContents(), $matches)) {
                // Get all matches
                foreach ($matches[2] as $key) {
                    $keys[] = $key;
                }
            }
        }
        // Remove duplicates
        $keys = array_unique($keys);

        // Add the translations to the database, if not existing.
        foreach ($keys as $key) {
            // Split the group and item
            list($group, $item) = explode('.', $key, 2);
            $this->missingKey('', $group, $item);
        }

        // Return the number of found translations
        return count($keys);
    }

    /**
     * Export translation from database to filesystem.
     *
     * @param string $group
     */
    public function exportTranslations($group)
    {
        if (!in_array($group, $this->config['exclude_groups'])) {
            if ($group == '*') {
                return $this->exportAllTranslations();
            }

            $tree = $this->makeTree(Translation::where('group', $group)->whereNotNull('value')->get());

            foreach ($tree as $locale => $groups) {
                if (isset($groups[$group])) {
                    $translations = $groups[$group];
                    $path         = $this->app->langPath() . '/' . $locale . '/' . $group . '.php';
                    $output       = "<?php\n\nreturn " . var_export($translations, true) . ";\n";
                    $this->files->put($path, $output);
                }
            }
            Translation::where('group', $group)->whereNotNull('value')->update(['status' => Translation::STATUS_SAVED]);
        }
    }

    /**
     * Export all the translations
     */
    public function exportAllTranslations()
    {
        $groups = Translation::whereNotNull('value')->select(DB::raw('DISTINCT `group`'))->get('group');

        foreach ($groups as $group) {
            $this->exportTranslations($group->group);
        }
    }

    /**
     * Clean the translations in the database.
     */
    public function cleanTranslations()
    {
        Translation::whereNull('value')->delete();
    }

    /**
     * Truncate the translations in the database.
     */
    public function truncateTranslations()
    {
        Translation::truncate();
    }

    /**
     * Create translations tree.
     *
     * @param array $translations
     *
     * @return array
     */
    protected function makeTree($translations)
    {
        $array = [];

        foreach ($translations as $translation) {
            array_set($array[$translation->locale][$translation->group], $translation->key, $translation->value);
        }

        return $array;
    }

    /**
     * Get a translation option.
     *
     * @param string|null $key
     *
     * @return mixed
     */
    public function getConfig($key = null)
    {
        if (is_null($key)) {
            return $this->config;
        }

        if (!isset($this->config[$key])) {
            return null;
        }

        return $this->config[$key];
    }
}
