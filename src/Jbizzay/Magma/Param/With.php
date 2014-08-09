<?php namespace Jbizzay\Magma\Param;

class With extends AbstractParam {
  
  public static function query($query, $value)
  {
    $query->with($value);
  }

}