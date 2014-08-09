<?php namespace Jbizzay\Magma\Param;

class OrderBy extends AbstractParam {
  
  public static function query($query, $column, $dir = 'asc')
  {
    $query->orderBy($column, $dir);
  }

}