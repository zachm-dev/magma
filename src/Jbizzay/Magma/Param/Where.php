<?php namespace Jbizzay\Magma\Param;

class Where extends AbstractParam {
  
  public static function query($query, $column, $value = null, $operator = '=')
  {
  	// Check for a relations query
  	if (strstr($column, '.')) {
  		$parts = explode('.', $column);
  		$query->whereHas($parts[0], function ($q) use ($parts, $value) {
  			$q->where($parts[1], $value);
  		});
  	} else {
    	$query->where($column, $operator, $value);
	}
  }

}