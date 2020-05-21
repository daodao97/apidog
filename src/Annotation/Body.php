<?php
declare(strict_types=1);
namespace Hyperf\Apidog\Annotation;

use Hyperf\Di\Annotation\AbstractAnnotation;
use Hyperf\Utils\Arr;

/**
 * @Annotation
 * @Target({"METHOD"})
 */
class Body extends Param
{
    public $in = 'body';
    public $name = 'body';
    public $rules;
    public $description = 'body';

    public function __construct($value = null)
    {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $val;
                }
            }
        }
        $this->setRequire()->setType();
    }

    public function setRequire()
    {
        $this->required = strpos(json_encode($this->rules), 'required') !== false;
        return $this;
    }

    public function setType()
    {
        $this->type = '';

        return $this;
    }
}
