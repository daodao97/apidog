<?php
declare(strict_types=1);
namespace Hyperf\Apidog\Swagger;

use Hyperf\Apidog\Annotation\ApiResponse;
use Hyperf\Apidog\Annotation\Body;
use Hyperf\Apidog\Annotation\FormData;
use Hyperf\Apidog\Annotation\Param;
use Hyperf\Apidog\ApiAnnotation;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;

class SwaggerJson
{
    public $config;

    public $swagger;

    public $logger;

    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->config = $container->get(ConfigInterface::class);
        $this->logger = $container->get(LoggerFactory::class)->get('apidog');
        $this->swagger = $this->config->get('apidog.swagger');
    }

    public function addPath($className, $methodName)
    {
        $classAnnotation = ApiAnnotation::classMetadata($className);
        $methodAnnotations = ApiAnnotation::methodMetadata($className, $methodName);
        if (!$classAnnotation || !$methodAnnotations) {
            return;
        }
        $params = [];
        $responses = [];
        /** @var \Hyperf\Apidog\Annotation\GetApi $mapping */
        $mapping = null;
        $consumes = null;
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
            if ($option instanceof FormData) {
                $consumes = 'application/x-www-form-urlencoded';
            }
            if ($option instanceof Body) {
                $consumes = 'application/json';
            }
        }
        $tag = $classAnnotation->tag ?: $className;
        $this->swagger['tags'][$tag] = [
            'name' => $tag,
            'description' => $classAnnotation->description,
        ];

        $path = $mapping->path;
        $prefix = $classAnnotation->prefix;
        if ($path === '') {
            $path = $prefix;
        } elseif ($path[0] !== '/') {
            $path = $prefix . '/' . $path;
        }
        $method = strtolower($mapping->methods[0]);
        $this->swagger['paths'][$path][$method] = [
            'tags' => [
                $tag,
            ],
            'summary' => $mapping->summary,
            'operationId' => implode('', array_map('ucfirst', explode('/', $path))) . $mapping->methods[0],
            'parameters' => $this->makeParameters($params, $path),
            'consumes' => [
                "application/json",
            ],
            'produces' => [
                "application/json",
            ],
            'responses' => $this->makeResponses($responses, $path, $method),
            'description' => $mapping->description,
        ];
        if ($consumes !== null) {
            $this->swagger['paths'][$path][$method]['consumes'] = $consumes;
        }
    }

    public function initModel()
    {
        $arraySchema = [
            'type' => 'array',
            'required' => [],
            'items' => [
                'type' => 'string'
            ],
        ];
        $objectSchema = [
            'type' => 'object',
            'required' => [],
            'items' => [
                'type' => 'string'
            ],
        ];

        $this->swagger['definitions']['ModelArray'] = $arraySchema;
        $this->swagger['definitions']['ModelObject'] = $objectSchema;
    }

    public function rules2schema($rules)
    {
        $schema = [
            'type' => 'object',
            'required' => [],
            'properties' => [],
        ];
        foreach ($rules as $field => $rule) {
            $property = [];
            $fieldNameLabel = explode('|', $field);
            $fieldName = $fieldNameLabel[0];
            if (!is_array($rule)) {
                $type = $this->getTypeByRule($rule);
            } else {
                //TODO 结构体多层
                $type = 'string';
            }
            if ($type == 'array') {
                $property['$ref'] = '#/definitions/ModelArray';
            }
            if ($type == 'object') {
                $property['$ref'] = '#/definitions/ModelObject';
            }
            $property['type'] = $type;
            $property['description'] = $fieldNameLabel[1] ?? '';
            $schema['properties'][$fieldName] = $property;
        }

        return $schema;
    }

    public function getTypeByRule($rule)
    {
        $default = explode('|', preg_replace('/\[.*\]/', '', $rule));
        if (array_intersect($default, ['int', 'lt', 'gt', 'ge'])) {
            return 'integer';
        }
        if (array_intersect($default, ['array'])) {
            return 'array';
        }
        if (array_intersect($default, ['object'])) {
            return 'object';
        }
        return 'string';
    }

    public function makeParameters($params, $path)
    {
        $this->initModel();
        $path = str_replace(['{', '}'], '', $path);
        $parameters = [];
        /** @var \Hyperf\Apidog\Annotation\Query $item */
        foreach ($params as $item) {
            if ($item->rule !== null && in_array('array', explode('|', $item->rule))) {
                $item->name .= '[]';
            }
            $parameters[$item->name] = [
                'in' => $item->in,
                'name' => $item->name,
                'description' => $item->description,
                'required' => $item->required,
                'type' => $item->type,
                'default' => $item->default,
            ];
            if ($item instanceof Body) {
                $modelName = implode('', array_map('ucfirst', explode('/', $path)));
                $schema = $this->rules2schema($item->rules);
                $this->swagger['definitions'][$modelName] = $schema;
                $parameters[$item->name]['schema']['$ref'] = '#/definitions/' . $modelName;
            }
        }

        return array_values($parameters);
    }

    public function makeResponses($responses, $path, $method)
    {
        $path = str_replace(['{', '}'], '', $path);
        $resp = [];
        /** @var ApiResponse $item */
        foreach ($responses as $item) {
            $resp[$item->code] = [
                'description' => $item->description,
            ];
            if ($item->schema) {
                $modelName = implode('', array_map('ucfirst', explode('/', $path))) . ucfirst($method) . 'Response' . $item->code;
                $ret = $this->responseSchemaToDefinition($item->schema, $modelName);
                if ($ret) {
                    $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $modelName;
                }
            }
        }

        return $resp;
    }

    public function responseSchemaToDefinition($schema, $modelName, $level = 0)
    {
        if (!$schema) {
            return false;
        }
        $definition = [];
        foreach ($schema as $key => $val) {
            $_key = str_replace('_', '', $key);
            $property = [];
            $property['type'] = gettype($val);
            if (is_array($val)) {
                $definitionName = $modelName . ucfirst($_key);
                if ($property['type'] == 'array' && isset($val[0])) {
                    if (is_array($val[0])) {
                        $property['type'] = 'array';
                        $ret = $this->responseSchemaToDefinition($val[0], $definitionName, 1);
                        $property['items']['$ref'] = '#/definitions/' . $definitionName;
                    } else {
                        $property['type'] = 'array';
                        $property['items']['type'] = gettype($val[0]);
                    }
                } else {
                    $property['type'] = 'object';
                    $ret = $this->responseSchemaToDefinition($val, $definitionName, 1);
                    $property['$ref'] = '#/definitions/' . $definitionName;
                }
                if (isset($ret)) {
                    $this->swagger['definitions'][$definitionName] = $ret;
                }
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
        $outputFile = $this->config->get('apidog.output_file');
        if (!$outputFile) {
            $this->logger->error('/config/autoload/apidog.php need set output_file');
            return;
        }
        file_put_contents($outputFile, json_encode($this->swagger, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->logger->debug('Generate swagger.json success!');
    }
}
