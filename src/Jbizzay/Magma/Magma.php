<?php namespace Jbizzay\Magma;

use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;

use Confide;

class Magma {

    /**
     * Query parameters that magma will key on
     * key => ClassName
     */
    protected static $map = [
        'order_by' => 'OrderBy',
        'skip' => 'Skip',
        'take' => 'Take',
        'where' => 'Where',
        'with' => 'With'
    ];

    /**
     * Create a new model record
     * Returns newly created resource in basic form
     * @param string $model
     *   A model class e.g. User
     * @param array $values
     *   Any values to explictly set and/or override hydration
     * @return Response
     */
    public static function create($model, $values = [], $onSuccess = null)
    {
        if ( ! MagmaAccess::access($model, 'create')) {
            return static::responseAccessDenied();
        }
        $record = new $model;

        $record->autoHydrateEntityFromInput = false;
        $record->forceEntityHydrationFromInput = false;
        $record->autoPurgeRedundantAttributes = true;

        $relations = static::getRelations($record);

        $input = Input::all();
        if ($input) {
            $fill = [];
            foreach ($input as $key => $value) {
                if ($relations && isset($relations[$key])) {
                    continue;
                }
                if (MagmaAccess::accessField($record, 'create', $key)) {
                    $fill[$key] = $value;
                }
            }
            $record->fill($fill);
        }

        if ( ! empty($values)) {
            foreach ($values as $key => $value) {
                $record->$key = $value;
            }
        }
        $values = array_merge($input, $values);

        if ($record->save()) {
            static::syncRelations($record, $values, 'create');
            if ($onSuccess) {
                $onSuccess($record);
            }
            return $record;
        }
        return Response::json(['errors' => $record->errors()->all(':message')], 403);
    }

    /**
     * Delete a model record
     * @param string $model
     *   A model class e.g. User
     * @param integer $id
     *   ID of the model record
     * @param function $onSuccess
     *   Callback function when successful
     * @return Response
     */
    public static function delete($model, $id, $onSuccess = null)
    {
        $record = static::findRecord($model, $id);
        if ( ! $record) {
            return Response::json(['errors' => [ucwords($model) .' not found']], 403);
        }
        if ( ! MagmaAccess::access($record, 'delete')) {
            return static::responseAccessDenied();
        }
        // Detach from relations
        // Needed if database is not using onDelete cascade
        $relations = static::getRelations($record);
        if ($relations) {
            foreach ($relations as $name => $relation) {
                switch ($relation[0]) {
                    case 'hasOne':

                    break;
                    case 'belongsToMany':
                        $record->$name()->detach();
                    break;
                }
            }
        }
        if ($record->delete()) {
            if ($onSuccess) {
                $onSuccess($record);
            }
            return ['success' => 'OK'];
        }
        return Response::json(['errors' => ["Couldn't delete $model"]], 403);
    }

    /**
     * Find a model record
     * Does Input magic
     * @param string $model
     *   A model class
     * @param integer $id
     *   ID of the model
     * @return object
     *   Record or null
     */
    public static function findRecord($model, $id)
    {
        $query = $model::query();
        static::setupQueryFromInput($query);
        $record = $query->find($id);
        if ($record) {
            return $record;
        }
        return null;
    }

    /**
     * Go find all the custom models in the app
     * @return array
     *   Models
     */
    public static function getModels()
    {
        $files = \File::glob(app_path() .'/models/*.php');
        $models = [];
        foreach ($files as $file) {
            preg_match('~/([^/]*).php~', $file, $match);
            $models[] = $match[1];
        }
        return $models;
    }

    /**
     * Get a model's relations
     * Depends on ardent relation definitions
     * @param string $model
     * @return array
     *   relations
     */
    public static function getRelations($model)
    {
        $relations = $model::$relationsData;
        return $relations;
    }

    /**
     * Query a model
     * Uses Input to do magic
     * @param string $model
     *   A model class e.g. User
     * @return object Illuminate\Database\Eloquent\Collection aka Response
     *   Return this in your controller or route callback
     */
    public static function query($model)
    {
        $query = $model::query();
        static::setupQueryFromInput($query);
        return $query->get();
    }

    /**
     * Find a model record
     * Use Input to do some more magic
     * @param string $model
     *   A model class e.g. User
     * @param integer $id
     *   ID of the model
     * @return Response
     */
    public static function read($model, $id)
    {
        $record = static::findRecord($model, $id);
        if ($record) {

            $attrs = $record->getAttributes();
            foreach ($attrs as $key => $value) {
                if ( ! MagmaAccess::accessField($record, 'read', $key)) {
                    unset($record->$key);
                }
            }
            $relations = static::getRelations($record);
            if ($relations) {
                foreach ($relations as $key => $value) {
                    if ( ! MagmaAccess::accessField($record, 'read', $key)) {
                        unset($record->$key);
                    }
                }
            }

            return $record;
        }
        return Response::json(['errors' => [ucwords($model) .' not found']], 403);
    }

    protected static function responseAccessDenied()
    {
        return Response::json(['errors' => ["Access Denied"]], 401);
    }

    /**
     * Setup query, do magic
     */
    protected static function setupQueryFromInput($query)
    {
        $input = Input::all();

        if ($input) {
            foreach ($input as $key => $value) {
                if ( ! empty($value) && isset(static::$map[$key])) {
                    $className = 'Jbizzay\\Magma\\Param\\'. static::$map[$key];
                    $value = (array) $value;
                    foreach ($value as $v) {
                        $values = $className::parseValue($v);
                        $pass = array_merge([$query], $values);
                        call_user_func_array([$className, 'query'], $pass);
                    }
                }
            }
        }
    }

    /**
     * Sync everything with the database
     * @todo: Let models declare their schema and sync here
     * @todo: Call MagmaAccess::sync to sync permissions
     */
    public static function syncDatabase()
    {

    }

    /**
     * Update/create a model record's relations
     * This handles syncing, so whatever is passed here will
     * be the atomic value of the relation
     * @param object $record
     *   Ardent model instance
     * @param array $values
     *   Input values to search for relations
     * @return void
     */
    public static function syncRelations($record, $values, $action)
    {
        $relations = static::getRelations($record);
        if ($relations) {
            foreach ($relations as $name => $relation) {
                // Check user has access to perform operation on relation
                if (in_array($action, ['create', 'update'])) {
                    $user = Confide::user();
                    if (isset($record::$accessRules) && ! empty($record::$accessRules['fields']) && ! empty($record::$accessRules['fields'][$name][$action])) {
                        $access = false;
                        $roles = (array) $record::$accessRules['fields'][$name][$action]['roles'];
                        foreach ($roles as $role) {
                            if ($user && $user->hasRole($role)) {
                                $access = true;
                            }
                        }
                        if ( ! $access) {
                            continue;
                        }
                    }
                }
                if (isset($values[$name])) {
                    switch ($relation[0]) {
                        case 'hasOne':

                        break;
                        case 'belongsTo':

                        break;
                        case 'belongsToMany':
                            $syncValues = [];
                            foreach ($values[$name] as $key => $value) {
                                if ( ! empty($value[0]['id'])) {
                                    foreach ($value as $relValue) {
                                        $syncValues[] = $relValue['id'];
                                    }
                                } elseif ( ! empty($value['id'])) {
                                    $syncValues[] = $value['id'];
                                } elseif ($value) {
                                    $syncValues[] = $value;
                                }
                            }
                            // Input should be an array of ids, do sync
                            $record->$name()->sync($syncValues);
                            // Make sure the updated relation is set on the model
                            $record->$name;
                        break;
                    }
                }
            }
        }
    }

    /**
     * Update a model record
     * Returns updated resource in basic form
     * @param string $model
     *   A model class e.g. User
     * @param integer $id
     *   ID of the model record
     * @param array $values
     *   Any values to explicitly set and/or override hydration
     * @return Response
     */
    public static function update($model, $id, $values = [], $onSuccess = null)
    {
        $record = $model::find($id);

        if ( ! $record) {
            return Response::json(['errors' => [ucwords($model) .' not found']], 403);
        }

        $record->autoHydrateEntityFromInput = false;
        $record->forceEntityHydrationFromInput = false;
        $record->autoPurgeRedundantAttributes = true;

        $relations = static::getRelations($record);

        $input = Input::all();
        if ($input) {
            $fill = [];
            foreach ($input as $key => $value) {
                if ($relations && isset($relations[$key])) {
                    continue;
                }

                if (MagmaAccess::accessField($record, 'update', $key)) {
                    $fill[$key] = $value;
                }
            }
            $record->fill($fill);
        }

        if ($values) {
            foreach ($values as $key => $value) {
                $record->$key = $value;
            }
        }

        $values = array_merge($input, $values);

        if ( ! MagmaAccess::access($record, 'update')) {
            return static::responseAccessDenied();
        }

        if ($record->updateUniques()) {
            // Update relations
            static::syncRelations($record, $values, 'update');

            if ($onSuccess) {
                // If success callback returns something, return that instead of record
                $return = $onSuccess($record);
                if ($return) {
                    return $return;
                }
            }
            return $record;
        }
        return Response::json(['errors' => $record->errors()->all(':message')], 403);
    }

}
