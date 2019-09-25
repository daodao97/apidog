<?php
namespace Hyperf\Apidog\Swagger;

use Hyperf\Apidog\Annotation\ApiResponse;
use Hyperf\Apidog\Annotation\Body;
use Hyperf\Apidog\Annotation\Param;
use Hyperf\Apidog\ApiAnnotation;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\Utils\ApplicationContext;

class SwaggerJson
{

    public $config;

    public $swagger;

    public function __construct()
    {
        $this->config = ApplicationContext::getContainer()
                                          ->get(ConfigInterface::class);
        $this->swagger = $this->config->get('swagger');
    }

    public function addPath($className, $methodName)
    {
        $classAnnotation = ApiAnnotation::classMetadata($className);
        $methodAnnotations = ApiAnnotation::methodMetadata($className, $methodName);
        $params = [];
        $responses = [];
        /** @var \Hyperf\Apidog\Annotation\GetApi $mapping */
        $mapping = null;
        foreach ($methodAnnotations as $option) {
            if ($option instanceof Mapping) {
                $mapping = $option;
            }
            if ($option instanceof Param) {
                $params[] = $option;
            }
            if ($option instanceof ApiResponse) {
                $responses[] = $option;
            }
        }
        $tag = $classAnnotation->tag ?: $className;
        $this->swagger['tags'][$tag] = [
            'name' => $tag,
            'description' => $classAnnotation->description,
        ];
        $base_path = $this->basePath($className);
        $path = $base_path . '/' . $methodName;
        if ($mapping->path) {
            $path = $mapping->path;
        }
        $method = strtolower($mapping->methods[0]);
        $this->swagger['paths'][$path][$method] = [
            'tags' => [
                $tag,
            ],
            'summary' => $mapping->summary,
            'parameters' => $this->makeParameters($params, $path),
            'consumes' => [
                "application/json",
            ],
            'produces' => [
                "application/json",
            ],
            'responses' => $this->makeResponses($responses, $path),
            'description' => $mapping->description,
        ];

    }

    public function basePath($className)
    {
        return controllerNameToPath($className);
    }

    public function makeParameters($params, $path)
    {
        $parameters = [];
        /** @var \Hyperf\Apidog\Annotation\Query $item */
        foreach ($params as $item) {
            $parameters[$item->name] = [
                'in' => $item->in,
                'name' => $item->name,
                'description' => $item->description,
                'required' => $item->required,
                'type' => $item->type,
            ];
            if ($item instanceof Body) {
                $modelName = implode('', array_map('ucfirst', explode('/', $path)));
                $this->swagger['definitions'][$modelName] = $item->schema;
                $parameters[$item->name]['schema']['$ref'] = '#/definitions/' . $modelName;
            }
        }

        return array_values($parameters);
    }

    public function makeResponses($responses, $path)
    {
        $path = str_replace(['{', '}'], '', $path);
        $resp = [];
        /** @var ApiResponse $item */
        foreach ($responses as $item) {
            $resp[$item->code] = [
                'description' => $item->description,
            ];
            if ($item->schema) {
                $modelName = implode('', array_map('ucfirst', explode('/', $path))) . 'Response' . $item->code;
                $this->responseSchemaTodefinition($item->schema, $modelName);
                $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $modelName;
            }
        }

        return $resp;
    }

    public function responseSchemaTodefinition($schema, $modelName, $level = 0)
    {
        $definition = [];
        foreach ($schema as $key => $val) {
            $property = [];
            $property['type'] = gettype($val);
            if (is_array($val)) {
                if ($property['type'] == 'array' && isset($val[0])) {
                    $property['type'] = 'array';
                    $ret = $this->responseSchemaTodefinition($val[0], $modelName . ucfirst($key), 1);
                    $property['items']['$ref'] = '#/definitions/' . $modelName . ucfirst($key);
                } else {
                    $property['type'] = 'object';
                    $ret = $this->responseSchemaTodefinition($val, $modelName . ucfirst($key), 1);
                    $property['$ref'] = '#/definitions/' . $modelName . ucfirst($key);
                }
                $this->swagger['definitions'][$modelName . ucfirst($key)] = $ret;
            } else {
                $property['default'] = $val;
            }
            $definition['properties'][$key] = $property;
        }
        if ($level === 0) {
            $this->swagger['definitions'][$modelName] = $definition;
        }

        return $definition;
    }

    public function save()
    {
        $this->swagger['tags'] = array_values($this->swagger['tags'] ?? []);
        $output_file = $this->swagger['output_file'] ?? '';
        if (!$output_file) {
            return;
        }
        unset($this->swagger['output_file']);
        file_put_contents($output_file, json_encode($this->swagger, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
