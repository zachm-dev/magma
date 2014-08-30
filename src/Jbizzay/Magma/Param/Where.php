<?php namespace Jbizzay\Magma\Param;

class Where extends AbstractParam {
  
  public static function query($query, $column, $value = null, $operator = '=')
  {
    $query->where($column, $operator, $value);
  }

}