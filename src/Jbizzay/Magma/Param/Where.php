<?php namespace Jbizzay\Magma\Param;

class Where extends AbstractParam {
  
  public static function query($query, $column, $value = null)
  {
    $query->where($column, $value);
  }

}