<?php
namespace TypiCMS\Models;

use App;
use Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use InvalidArgumentException;
use Input;
use Log;
use Route;

abstract class Base extends Model
{

    /**
     * Get preview uri
     *
     * @return null|string string or null
     */
    public function previewUri()
    {
        if (! $this->id) {
            return null;
        }
        return $this->getPublicUri(true);
    }

    /**
     * Get public uri
     *
     * @return string|null string or null
     */
    public function getPublicUriIndex()
    {
        $uri = $this->getPublicUri(false, true);
        return $uri;
    }

    /**
     * Get public uri
     *
     * @return string|null string or null
     */
    public function getPublicUri($preview = false, $index = false, $lang = null)
    {
        if (! $this->hasTranslation($lang)) {
            return null;
        }

        $lang = $lang ? : App::getlocale() ;

        // Route parameters
        $parameters = [$this->translate($lang)->slug];

        // If index of module is asked
        if ($index) {
            $parameters = [null];
        }

        // If model is offline and we are not in preview mode
        if (! $preview && ! $this->translate($lang)->status) {
            $parameters = [null];
        }

        $route = array();

        // Route name
        $route['lang'] = $lang;
        $route['table'] = $this->getTable();

        // if there is a category
        if (method_exists($this, 'category')) {
            if ($this->category) {
                array_unshift($parameters, $this->category->translate($lang)->slug);
                $route['category'] = 'categories';
            }
        }
        $route['suffix'] = 'slug';
        $routeName = implode('.', $route);

        // Does route exists ?
        if (Route::has($routeName)) {
            return route($routeName, $parameters);
        }
        return null;
    }

    /**
     * Attach files to model
     *
     * @param  Builder $query
     * @param  boolean $all : all models or online models
     * @return Builder $query
     */
    public function scopeFiles(Builder $query, $all = false)
    {
        return $query->with(
            array('files' => function (Builder $query) use ($all) {
                $query->with(array('translations' => function (Builder $query) use ($all) {
                    $query->where('locale', App::getLocale());
                    ! $all && $query->where('status', 1);
                }));
                $query->whereHas('translations', function (Builder $query) use ($all) {
                    $query->where('locale', App::getLocale());
                    ! $all && $query->where('status', 1);
                });
                $query->orderBy('position', 'asc');
            })
        );
    }

    /**
     * Get models that have online non empty translation
     *
     * @param  Builder $query
     * @return Builder $query
     */
    public function scopeOnline(Builder $query)
    {
        if (method_exists($this, 'translations')) {
            return $query->whereHas(
                'translations',
                function (Builder $query) {
                    if (! Input::get('preview')) {
                        $query->where('status', 1);
                    }
                    $query->where('locale', App::getLocale());
                }
            );
        } else {
            return $query->where('status', 1);
        }
    }

    /**
     * Get online galleries
     *
     * @param  Builder $query
     * @return Builder $query
     */
    public function scopeWithOnlineGalleries(Builder $query)
    {
        if (! method_exists($this, 'galleries')) {
            return $query;
        }
        return $query->with(
            array(
                'galleries.translations',
                'galleries.files.translations',
                'galleries' => function (MorphToMany $query) {
                    $query->whereHas(
                        'translations',
                        function (Builder $query) {
                            $query->where('status', 1);
                            $query->where('locale', App::getLocale());
                        }
                    );
                }
            )
        );
    }

    /**
     * Order items according to GET value or model value, default is id asc
     *
     * @param  Builder $query
     * @return Builder $query
     */
    public function scopeOrder(Builder $query)
    {
        if ($order = config('typicms.' . $this->getTable() . '.order')) {
            foreach ($order as $column => $direction) {
                $query->orderBy($column, $direction);
            }
        }
        return $query;
    }

    /**
     * Get title attribute from translation table
     * and append it to main model attributes
     * @return string title
     */
    public function getTitleAttribute($value)
    {
        return $this->title;
    }

    /**
     * Get status attribute from translation table
     * and append it to main model attributes
     * @return string title
     */
    public function getStatusAttribute($value)
    {
        return $this->status;
    }

    /**
     * Get status attribute from translation table
     * and append it to main model attributes
     * @return string title
     */
    public function getThumbAttribute($value)
    {
        return $this->present()->thumbSrc(null, 22);
    }

    /**
     * A model has many tags.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function tags()
    {
        return $this->morphToMany('TypiCMS\Modules\Tags\Models\Tag', 'taggable')
            ->orderBy('tag')
            ->withTimestamps();
    }

    /**
     * Get back office’s edit url of model
     * 
     * @return string|void
     */
    public function editUrl()
    {
        try {
            return route('admin.' . $this->getTable() . '.edit', $this->id);
        } catch (InvalidArgumentException $e) {
            Log::error($e->getMessage());
        }
    }

    /**
     * Get back office’s index of models url
     * 
     * @return string|void
     */
    public function indexUrl()
    {
        try {
            return route('admin.' . $this->getTable() . '.index');
        } catch (InvalidArgumentException $e) {
            Log::error($e->getMessage());
        }
    }

    /**
     * Generic Translate method to maintain compatibility 
     * when a model doesn't have Translatable trait.
     * @param  string $lang
     * @return $this
     */
    public function translate($lang = null)
    {
        return $this;
    }

    /**
     * Models without translatable trait doesn’t have translation.
     * 
     * @param  string  $locale
     * @return boolean
     */
    public function hasTranslation($locale)
    {
        return false;
    }
}
