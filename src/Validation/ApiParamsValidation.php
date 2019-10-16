<?php
namespace Hyperf\Apidog\Validation;

use Hyperf\Di\Annotation\Inject;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;

class ApiParamsValidation implements ValidationInterface
{

    /**
     * @Inject()
     * @var \Hyperf\Validation\Contract\ValidatorFactoryInterface
     */
    public $validator;

    /**
     * @Inject()
     * @var \Hyperf\Logger\LoggerFactory
     */
    public $logger;

    public $errors = [];
    public function check(array $rules, array $data, $obj = null, $keyTree = null)
    {
        $this->errors = [];
        $realRules = [];
        foreach ($rules as $field => $rule) {
            $fieldNameLabel = explode('|', $field);
            $fieldName = $fieldNameLabel[0];
            $tree = $keyTree ? $keyTree . '.' . $fieldName : $fieldName;
            if (is_array($rule)) {
                $ret = $this->check($rule, Arr::get($data, $fieldName, []), $obj, $tree);
                if ($ret === false) {
                    return false;
                }
                continue;
            }
            $constraints = array_filter(explode('|', $rule), function($item) {
                return !Str::startsWith($item, 'cb_');
            });

            $realRules[$fieldName] = implode('|', $constraints);
        }

        $validator = $this->validator->make($data, $realRules);

        $finalData = $validator->validate();

        foreach ($rules as $field => $rule) {
            $fieldLabel = $fieldNameLabel[1] ?? '';
            $field_value = Arr::get($data, $fieldName);
            $constraints = explode('|', $rule);
            $is_required = in_array('required', $constraints);
            if (!$is_required && is_null($field_value)) {
                continue;
            }
            foreach ($constraints as $constraint) {
                preg_match('/:(.*)/', $constraint, $m);
                $func = preg_replace('/:.*/', '', $constraint);
                $option = $m[1] ?? null;
                $funcFilter = 'filter_' . $func;
                if (method_exists($this, $funcFilter)) {
                    $filterValue = call_user_func_array([
                        $this,
                        $funcFilter,
                    ], [$field_value, $option]);
                    $this->log()->info(sprintf('validation key:%s filter:%s result:%s', $fieldName, $funcFilter, $filterValue));
                    $finalData[$fieldName] = $filterValue;
                }
                $customMethod = str_replace('cb_', '', $func);
                if (strpos($func, 'cb_') !== false && method_exists($obj, $customMethod)) {
                    $check = $obj->$customMethod($field_value, $option);
                    if ($check === true) {
                        $finalData[$fieldName] = $field_value;
                    } else {
                        $this->errors[$fieldName] = $check;
                    }
                    $this->log()->info(sprintf('validation key:%s cb:%s result:%s', $fieldName, $customMethod, $check === true ? 'true' : 'false'));
                }
                if ($this->errors) {
                    $label = $fieldLabel ? $fieldLabel . '(' . $tree . ')' : $tree;
                    foreach ($this->errors as $index => $each) {
                        $this->errors[$index] = sprintf($each, $label);
                    }

                    return false;
                }
            }
        }

        $this->errors = array_merge($this->errors, $validator->errors()->getMessages());

        if ($this->errors) {
            return false;
        }

        return $finalData;
    }

    public function filter_integer($val)
    {
        return (int)$val;
    }

    public function getError()
    {
        return $this->errors;
    }

    public function log()
    {
        return $this->logger->get('validation');
    }
}
