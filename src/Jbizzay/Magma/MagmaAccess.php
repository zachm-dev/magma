<?php namespace Jbizzay\Magma;

use Confide;

/**
 * Controls access to:
 * CRUD operations on models
 * CRUD operations on model fields
 * CRUD operations on model relations
 * Rules include * (open to all), authed, unauthed, owner
 * and user roles
 *
 * Access is false by default if model::$accessRules is defined.
 * Access must be explicitly granted.
 *
 */
class MagmaAccess {

    /**
     * Determine if user has access to perform action on a model
     * @param string|object $model
     *   Model string or loaded model record
     * @param string $action
     *   create, read, update, delete
     * @return boolean
     *   Whether user has access
     */
    public static function access($model, $action)
    {
        if ( ! isset($model::$accessRules)) {
            return true;
        }
        // Get current user
        $user = Confide::user();
        // Check CRUD rules
        if ( ! empty($model::$accessRules[$action]['roles'])) {
            return static::accessRules($model, $model::$accessRules[$action]['roles']);
        }
        return false;
    }

    /**
     * Determine if user has access to a field or relation
     * Only applies to Create, Read and Update
     */
    public static function accessField($model, $action, $field)
    {
        if ( ! isset($model::$accessRules)) {
            return true;
        }
        // Get current user
        $user = Confide::user();
        if ( ! empty($model::$accessRules['fields'][$field][$action])) {
            // Field level access is defined, by default will return false
            // Check field action rules
            return static::accessRules($model, $model::$accessRules['fields'][$field][$action]['roles']);
        }
        return true;
    }

    protected static function accessRules($model, $rules)
    {
        $user = Confide::user();
        $rules = (array) $rules;
        // If * is set, this action is permitted for all
        if (in_array('*', $rules)) {
            return true;
        }
        // If authed is set, and the user is logged in
        if (in_array('authed', $rules) && $user && ! empty($user->id)) {
            return true;
        }
        // If unauthed is set, and the user is not logged in
        if (in_array('unauthed', $rules) && (! $user || empty($user->id))) {
            return true;
        }
        // If user is the owner of this record
        if (in_array('owner', $rules) && $user && static::userOwnsRecord($model, $user)) {
            return true;
        }
        // If user has role
        if ($user && $user->id) {
            foreach ($rules as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Determine whether a user is the owner of a record
     */
    public static function userOwnsRecord($model, $user)
    {
        if ($model->getTable() == 'users') {
            $ownerField = 'id';
        } else {
            $ownerField = 'user_id';
        }
        if ($user && ! empty($model->$ownerField) && ($model->$ownerField == $user->id)) {
            return true;
        }
        return false;
    }

    /**
     * Check user access against a model and field level rules
     * @return boolean
     *   True if user can access
     */
    public static function accessRulesFields($model, $accessRules, $action)
    {
        $access = true;
        $user = Confide::user();
        // Check field level rules
        if (in_array($action, ['read', 'update'])) {
            $dirty = $model->getDirty();
            if ($dirty) {
                foreach ($dirty as $field => $value) {
                    if (isset($accessRules[$field][$action])) {
                        $access = false;
                        $roles = (array) $accessRules[$field][$action]['roles'];
                        foreach ($roles as $role) {
                            if ($user && $user->hasRole($role)) {
                                $access = true;
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $access;
    }

    /**
     * Show a list of permissions set on app's models
     */
    public static function getAccessRules()
    {
        $models = Magma::getModels();
        $rules = [];
        $allRoles = \Role::all();
        $getRoles = function ($roles) use ($allRoles) {
            $return = [];
            $roles = (array) $roles;
            foreach ($roles as $role) {
                if ($role == '*') {
                    foreach ($allRoles as $allRole) {
                        $return[] = $allRole->name;
                    }
                } else {
                    foreach ($allRoles as $allRole) {
                        if ($allRole->name == $role) {
                            $return[] = $allRole->name;
                        }
                    }
                }
            }
            return $return;
        };
        foreach ($models as $model) {
            // Each model can define their own access rules
            // Rule name starts with model name for sorting purposes
            if ( ! empty($model::$accessRules)) {
                foreach ($model::$accessRules as $key => $rule) {
                    if (isset($rules['display_name'])) {
                        $rules[] = [
                            'name' => strtolower($model) . '_' . $key,
                            'display_name' => $rule['display_name'],
                            'model' => $model,
                            'roles' => $getRoles($rule['roles'])
                        ];
                    }
                }
            }
            // Same for field level rules
            if (isset($model::$accessRules) && ! empty($model::$accessRules['fields'])) {
                foreach ($model::$accessRules['fields'] as $fieldName => $fieldRules) {
                    foreach ($fieldRules as $key => $rule) {
                        $rules[] = [
                            'name' => strtolower($model) . '_field_' . $fieldName . '_' . $key,
                            'display_name' => $rule['display_name'],
                            'model' => $model,
                            'roles' => $getRoles($rule['roles'])
                        ];
                    }
                }
            }
        }
        return $rules;
    }

}

