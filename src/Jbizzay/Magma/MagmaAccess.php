<?php namespace Jbizzay\Magma;

use Confide;

class MagmaAccess {
	
	/**
     * Determine if user has access to perform action on a model
     */
    public static function access($model, $action)
    {
        $access = false;
        // Check if should skip access check
        if ( ! empty($GLOBALS['MAGMA_SKIP_ACCESS'])) {
            return true;
        }
        // If user is super user, allow
        $user = Confide::user();
        if ($user && $user->username == 'admin') {
        	return true;
        }
        // By default, return false for an action / permission
        // It must be explicitly granted
        if ( ! empty($model::$accessRules[$action])) {
        	if ( ! static::accessRules($model, $model::$accessRules[$action], $action)) {
        		return false;
        	}
        }
        // By default, return true for field level action
        if ( ! empty($model::$accessRulesFields)) {
            if ( ! static::accessRulesFields($model, $model::$accessRulesFields, $action)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check user access against a model and rules
     * @return boolean
     *   True if user can access
     */
    public static function accessRules($model, $accessRule, $action)
    {
    	$rules = [];
        $allowRoles = (array) $accessRule['roles'];
        $access = false;
        $user = Confide::user();
        foreach ($allowRoles as $role) {
            $rules[$role] = $role;
        }
        // If * is set, this action is open for all
        if (in_array('*', $rules)) {
            $access = true;
        } else {
            // If user owns this model and owner rule is set, allow
            if (in_array('owner', $rules)) {
                if ($model->getTable() == 'users') {
                    $ownerField = 'id';
                } else {
                    $ownerField = 'user_id';
                }
                if ($user && ! empty($model->$ownerField) && ($model->$ownerField == $user->id)) {
                    $access = true;
                } else {
                    unset($rules['owner']);
                }
            }
            // Check user has a role set in rule
            if ( ! empty($rules)) {
                foreach ($rules as $role) {
                    if ($user && $user->hasRole($role)) {
                        $access = true;
                        break;
                    }
                }
            }
        }
        return $access;
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
                    if (isset($accessRules[$field])) {
                        $access = false;
                        $roles = (array) $accessRules[$field];
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
                    $rules[] = [
                        'name' => strtolower($model) . '_' . $key,
                        'display_name' => $rule['display_name'],
                        'model' => $model,
                        'roles' => $getRoles($rule['roles'])
                    ];
                }
            }
            // Same for field level rules
            if ( ! empty($model::$accessRulesFields)) {
                foreach ($model::$accessRulesFields as $fieldName => $fieldRules) {
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

