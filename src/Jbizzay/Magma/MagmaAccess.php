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
        } else {
        	return false;
        }
        return true;
    }

    public static function accessRules($model, $accessRules, $action)
    {
    	$rules = [];
    	$accessRules = (array) $accessRules;
        $access = false;
        $user = Confide::user();
        foreach ($accessRules as $key => $rule) {
            $rules[$rule] = $rule;
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
            // Pass the roles set to entrust
            if ( ! empty($rules)) {
                foreach ($rules as $role) {
                    if ($user && $user->hasRole($role)) {
                        $access = true;
                        break;
                    }
                }
            }
        }

        // Check field level rules
        if (in_array($action, ['read', 'update']) && ! empty($model::$accessRules['fields'])) {
            $dirty = $model->getDirty();
            if ($dirty) {
                foreach ($dirty as $field => $value) {
                    if (isset($dirty[$field])) {
                        $access = false;
                        $roles = (array) $dirty[$field];
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

}

