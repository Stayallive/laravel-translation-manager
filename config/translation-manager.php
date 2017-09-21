<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Routes group config
    |--------------------------------------------------------------------------
    |
    | The default group settings for the elFinder routes.
    |
    */
    'route'          => array(
        'prefix'     => 'translations',
        'middleware' => 'auth',
    ),

    /**
     * Enable deletion of translations
     *
     * @type boolean
     */
    'delete_enabled' => true,

    /**
     * Enable creating of translations
     *
     * @type boolean
     */
    'creating_enabled' => true,

    /**
     * Enable import of translations
     *
     * @type boolean
     */
    'import_enabled' => true,

    /**
     * Enable find of translations
     *
     * @type boolean
     */
    'find_enabled' => true,

    /**
     * Exclude specific groups from Laravel Translation Manager.
     * This is useful if, for example, you want to avoid editing the official Laravel language files.
     *
     * @type array
     *
     *    array(
     *        'pagination',
     *        'reminders',
     *        'validation',
     *    )
     */
    'exclude_groups' => array(),

    /**
     * Export translations with keys output alphabetically.
     */
    'sort_keys '     => false,

    /**
     * The database connection to use.
     *
     * @type string|null
     */
    'db_connection'  => null,

    /**
     * Set the position of the menu in a translations group
     *
     * @type string    top|bottom
     */
    'menu_position' => 'top',

);
