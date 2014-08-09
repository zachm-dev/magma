<?php namespace Jbizzay\Magma\Param;

class Take extends AbstractParam {
  
  public static function query($query, $take)
  {
    $query->take($take);
  } 

}