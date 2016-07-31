<?php

namespace Barryvdh\TranslationManager;

use Illuminate\Translation\Translator as LaravelTranslator;

class Translator extends LaravelTranslator
{
    /**
     * Get the translation for the given key.
     *
     * @param string $key
     * @param array  $replace
     * @param string $locale
     * @param bool   $fallback
     *
     * @return string
     */
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        // Get without fallback
        $result = parent::get($key, $replace, $locale, false);

        if ($result === $key) {
            $this->notifyMissingKey($key);

            // Reget with fallback
            $result = parent::get($key, $replace, $locale, $fallback);
        }

        return $result;
    }

    /**
     * Set the translation manager.
     *
     * @param \Barryvdh\TranslationManager\Manager $manager
     */
    public function setTranslationManager(Manager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Notify about a missing key.
     *
     * @param string $key
     */
    protected function notifyMissingKey($key)
    {
        list($namespace, $group, $item) = $this->parseKey($key);

        if ($this->manager && $namespace === '*' && $group && $item) {
            $this->manager->missingKey($namespace, $group, $item);
        }
    }
}
