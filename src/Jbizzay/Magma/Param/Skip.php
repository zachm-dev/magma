<?php namespace Jbizzay\Magma\Param;

class Skip extends AbstractParam {
  
  public static function query($query, $skip)
  {
    $query->skip($skip);
  }

}