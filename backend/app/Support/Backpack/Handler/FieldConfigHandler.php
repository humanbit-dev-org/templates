<?php

namespace App\Support\Backpack\Handler;

class FieldConfigHandler
{
    /**
     * Get field configuration for select_from_array fields
     * This is a centralized configuration used by both FieldTypeHandler and FilterHelper
     *
     * @param  string  $column  Column name
     * @param  string  $table  Table name
     * @return array|null Returns ['type' => 'select_from_array', 'options' => [...]] or null
     */
    public static function getSelectFieldConfig($column, $table)
    {
        // Add per-table select fields here, e.g. a status enum:
        //
        //     if ($column === 'status' && $table === 'orders') {
        //         return ['type' => 'select_from_array', 'options' => ['open' => 'Open', 'paid' => 'Paid'], 'default' => 'open'];
        //     }

        // SEO metadata code field
        if ($table == 'metadata' && str_contains($column, 'code')) {
            return [
                'type' => 'select_from_array',
                'options' => [
                    'title' => 'title',
                    'description' => 'description',
                    'og_url' => 'og:url',
                    'og_site_name' => 'og:site_name',
                    'og_title' => 'og:title',
                    'og_description' => 'og:description',
                    'og_image' => 'og:image',
                    'og_locale' => 'og:locale',
                ],
            ];
        }

        return null;
    }

    /**
     * Check if a column should be a select field
     *
     * @param  string  $column  Column name
     * @param  string  $table  Table name
     * @return bool
     */
    public static function isSelectField($column, $table)
    {
        return self::getSelectFieldConfig($column, $table) !== null;
    }
}
