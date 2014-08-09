<?php namespace Jbizzay\Magma\Param;

class AbstractParam {
  
  /**
   * All params can be singular,
   * This should be true for params that can accept multiple values
   * E.g. user?with=image, or user?with[]=image&with[]=roles
   */
  protected $multiple = false;

  public static function query($query, $value) {

  }

  public static function parseValue($value)
  {
    $parts = explode(',', $value);
    if ($parts) {
      foreach ($parts as &$part) {
        $part = trim($part);
      }
    }
    return $parts;
  }

}