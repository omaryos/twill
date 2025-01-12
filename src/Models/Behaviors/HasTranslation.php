<?php

namespace A17\Twill\Models\Behaviors;

use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Query\JoinClause;
use A17\Twill\Services\Capsules\HasCapsules;

trait HasTranslation
{
    use Translatable, HasCapsules;

    public function getTranslationModelNameDefault()
    {
        $repository = config('twill.namespace') . "\Models\Translations\\" . class_basename($this) . 'Translation';

        if (@class_exists($repository)) {
            return $repository;
        }

        return $this->getCapsuleTranslationClass(class_basename($this));
    }

    public function scopeWithActiveTranslations($query, $locale = null)
    {
        if (method_exists($query->getModel(), 'translations')) {
            $locale = $locale == null ? app()->getLocale() : $locale;

            $query->whereHas('translations', function ($query) use ($locale) {
                $query->whereActive(true);
                $query->whereLocale($locale);

                if (config('translatable.use_property_fallback', false)) {
                    $query->orWhere('locale', config('translatable.fallback_locale'));
                }
            });

            return $query->with(['translations' => function ($query) use ($locale) {
                $query->whereActive(true);
                $query->whereLocale($locale);

                if (config('translatable.use_property_fallback', false)) {
                    $query->orWhere('locale', config('translatable.fallback_locale'));
                }
            }]);
        }
    }

    public function scopeOrderByTranslation($query, $orderField, $orderType = 'ASC', $locale = null)
    {
        $translationTable = $this->getTranslationsTable();
        $localeKey = $this->getLocaleKey();
        $table = $this->getTable();
        $keyName = $this->getKeyName();
        $locale = $locale == null ? app()->getLocale() : $locale;

        return $query
            ->join($translationTable, function (JoinClause $join) use ($translationTable, $localeKey, $table, $keyName) {
                $join
                    ->on($translationTable.'.'.$this->getRelationKey(), '=', $table.'.'.$keyName)
                    ->where($translationTable.'.'.$localeKey, $this->locale());
            })
            ->where($translationTable.'.'.$this->getLocaleKey(), $locale)
            ->orderBy($translationTable.'.'.$orderField, $orderType)
            ->select($table.'.*')
            ->with('translations');
    }

    public function scopeOrderByRawByTranslation($query, $orderRawString, $groupByField, $locale = null)
    {
        $translationTable = $this->getTranslationsTable();
        $table = $this->getTable();
        $locale = $locale == null ? app()->getLocale() : $locale;

        return $query->join("{$translationTable} as t", "t.{$this->getRelationKey()}", "=", "{$table}.id")
            ->where($this->getLocaleKey(), $locale)
            ->groupBy("{$table}.id")
            ->groupBy("t.{$groupByField}")
            ->select("{$table}.*")
            ->orderByRaw($orderRawString)
            ->with('translations');
    }

    public function hasActiveTranslation($locale = null)
    {
        $locale = $locale ?: $this->locale();

        $translations = $this->memoizedTranslations ?? ($this->memoizedTranslations = $this->translations()->get());

        foreach ($translations as $translation) {
            if ($translation->getAttribute($this->getLocaleKey()) == $locale && $translation->getAttribute('active')) {
                return true;
            }
        }

        return false;
    }

    public function getActiveLanguages()
    {
        return $this->translations->map(function ($translation) {
            return [
                'shortlabel' => strtoupper($translation->locale),
                'label' => getLanguageLabelFromLocaleCode($translation->locale),
                'value' => $translation->locale,
                'published' => $translation->active ?? false,
            ];
        })->sortBy(function ($translation) {
            $localesOrdered = config('translatable.locales');
            return array_search($translation['value'], $localesOrdered);
        })->values();
    }

    public function translatedAttribute($key)
    {
        return $this->translations->mapWithKeys(function ($translation) use ($key) {
            return [$translation->locale => $this->translate($translation->locale)->$key];
        });
    }

}
