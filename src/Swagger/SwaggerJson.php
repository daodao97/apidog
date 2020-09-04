<?php
declare(strict_types=1);

namespace Hyperf\Apidog\Swagger;

use Doctrine\Common\Annotations\AnnotationReader;
use Hyperf\Apidog\Annotation\ApiController;
use Hyperf\Apidog\Annotation\ApiDefinition;
use Hyperf\Apidog\Annotation\ApiDefinitions;
use Hyperf\Apidog\Annotation\ApiResponse;
use Hyperf\Apidog\Annotation\ApiServer;
use Hyperf\Apidog\Annotation\ApiVersion;
use Hyperf\Apidog\Annotation\Body;
use Hyperf\Apidog\Annotation\FormData;
use Hyperf\Apidog\Annotation\Param;
use Hyperf\Apidog\ApiAnnotation;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Arr;

class SwaggerJson
{
    public $config;

    public $swagger;

    public $logger;

    public $server;

    public function __construct($server)
    {
        $container = ApplicationContext::getContainer();
        $this->config = $container->get(ConfigInterface::class);
        $this->logger = $container->get(LoggerFactory::class)->get('apidog');
        $this->swagger = $this->config->get('apidog.swagger');
        $this->server = $server;
    }

    public function addPath($className, $methodName)
    {
        $ignores = $this->config->get('annotations.scan.ignore_annotations', []);
        foreach ($ignores as $ignore) {
            AnnotationReader::addGlobalIgnoredName($ignore);
        }
        $classAnnotation = ApiAnnotation::classMetadata($className);
        $controlerAnno = $classAnnotation[ApiController::class] ?? null;
        $serverAnno = $classAnnotation[ApiServer::class] ?? null;
        $versionAnno = $classAnnotation[ApiVersion::class] ?? null;
        $definitionsAnno = $classAnnotation[ApiDefinitions::class] ?? null;
        $definitionAnno = $classAnnotation[ApiDefinition::class] ?? null;
        $bindServer = $serverAnno ? $serverAnno->name : $this->config->get('server.servers.0.name');

        $servers = $this->config->get('server.servers');
        $servers_name = array_column($servers, 'name');
        if (!in_array($bindServer, $servers_name)) {
            throw new \Exception(sprintf('The bind ApiServer name [%s] not found, defined in %s!', $bindServer, $className));
        }

        if ($bindServer !== $this->server) {
            return;
        }
        $methodAnnotations = ApiAnnotation::methodMetadata($className, $methodName);
        if (!$controlerAnno || !$methodAnnotations) {
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

        $this->makeDefinition($definitionsAnno);
        $definitionAnno && $this->makeDefinition([$definitionAnno]);
        
        $tag = $controlerAnno->tag ?: $className;
        $this->swagger['tags'][$tag] = [
            'name' => $tag,
            'description' => $controlerAnno->description,
        ];

        $path = $mapping->path;
        $prefix = $controlerAnno->prefix;
        if ($path === '') {
            $path = $prefix;
        } elseif ($path[0] !== '/') {
            $path = $prefix . '/' . $path;
        }
        if($versionAnno && $versionAnno->version) {
            $path = '/' . $versionAnno->version . $path;
        }
        $method = strtolower($mapping->methods[0]);
        $this->swagger['paths'][$path][$method] = [
            'tags' => [
                $tag,
            ],
            'summary' => $mapping->summary ?? '',
            'operationId' => implode('', array_map('ucfirst', explode('/', $path))) . $mapping->methods[0],
            'parameters' => $this->makeParameters($params, $path, $method),
            'produces' => [
                "application/json",
            ],
            'responses' => $this->makeResponses($responses, $path, $method),
            'description' => $mapping->description ?? '',
        ];
        if ($consumes !== null) {
            $this->swagger['paths'][$path][$method]['consumes'] = [$consumes];
        }
    }

    public function initModel()
    {
        $arraySchema = [
            'type' => 'array',
            'required' => [],
            'items' => [
                'type' => 'string',
            ],
        ];
        $objectSchema = [
            'type' => 'object',
            'required' => [],
            'items' => [
                'type' => 'string',
            ],
        ];

        $this->swagger['definitions']['ModelArray'] = $arraySchema;
        $this->swagger['definitions']['ModelObject'] = $objectSchema;
    }

    public function rules2schema($name, $rules)
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
                $property['type'] = $type;
                if ($type == 'array') {
                    $property['$ref'] = '#/definitions/ModelArray';
                }
                if ($type == 'object') {
                    $property['$ref'] = '#/definitions/ModelObject';
                }
            } else {
                $deepModelName = $name . ucfirst($fieldName);
                if (Arr::isAssoc($rule)) {
                    $type = 'object';
                    $this->rules2schema($deepModelName, $rule);
                    $property['$ref'] = '#/definitions/' . $deepModelName;
                } else {
                    $type = 'array';
                    $this->rules2schema($deepModelName, $rule[0]);
                    $property['items']['$ref'] = '#/definitions/' . $deepModelName;
                }
            }
            $property['type'] = $type;
            $property['description'] = $fieldNameLabel[1] ?? '';
            $schema['properties'][$fieldName] = $property;
        }

        $this->swagger['definitions'][$name] = $schema;
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
        if (array_intersect($default, ['file'])) {
            return 'file';
        }
        return 'string';
    }

    public function makeParameters($params, $path, $method)
    {
        $method = ucfirst($method);
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
            ];
            if ($item instanceof Body) {
                $modelName = $method . implode('', array_map('ucfirst', explode('/', $path)));
                $this->rules2schema($modelName, $item->rules);
                $parameters[$item->name]['schema']['$ref'] = '#/definitions/' . $modelName;
            } else {
                $type = $this->getTypeByRule($item->rule);
                $parameters[$item->name]['type'] = $type;
                $parameters[$item->name]['default'] = $item->default;
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
                if (isset($item->schema['$ref'])) {
                    $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $item->schema['$ref'];
                    continue;
                }
                $modelName = implode('', array_map('ucfirst', explode('/', $path))) . ucfirst($method) . 'Response' . $item->code;
                $ret = $this->responseSchemaToDefinition($item->schema, $modelName);
                if ($ret) {
                    $resp[$item->code]['schema']['$ref'] = '#/definitions/' . $modelName;
                }
            }
        }

        return $resp;
    }

    public function makeDefinition($definitions)
    {
        if (!$definitions) {
            return false;
        }
        if ($definitions instanceof ApiDefinitions) {
            $definitions = $definitions->definitions;
        }
        foreach ($definitions as $definition) {
            /** @var $definition ApiDefinition */
            $defName = $definition->name;
            $defProps = $definition->properties;
            $formattedProps = [];

            foreach ($defProps as $propKey => $prop) {
                $propKeyArr = explode('|', $propKey);
                $propName = $propKeyArr[0];
                $propVal = [];
                isset($propKeyArr[1]) && $propVal['description'] = $propKeyArr[1];
                if (is_array($prop)) {
                    if (isset($prop['description']) && is_string($prop['description'])) {
                        $propVal['description'] = $prop['description'];
                    }

                    if (isset($prop['type']) && is_string($prop['type'])) {
                        $propVal['type'] = $prop['type'];
                    }

                    if (isset($prop['default'])) {
                        $propVal['defalut'] = $prop['default'];
                        !isset($propVal['type']) && $propVal['type'] = is_numeric($default) ? 'integer': 'string';
                    }

                    if (isset($prop['$ref'])) {
                        $propVal['type'] = 'object';
                        $propVal['$ref'] = '#/definitions/' . $prop['$ref'];
                    }
                } else {
                    $propVal['defalut'] = $prop;
                    $propVal['type'] = is_numeric($prop) ? 'integer': 'string';
                }
                $formattedProps[$propName] = $propVal;
            }
            $this->swagger['definitions'][$defName]['properties'] = $formattedProps;
        }

    }

    public function responseSchemaToDefinition($schema, $modelName, $level = 0)
    {
        if (!$schema) {
            return false;
        }
        $definition = [];
        foreach ($schema as $keyString => $val) {
            $keyArray =  explode('|',$keyString);
            $key = $keyArray[0];
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
            $property['description'] = $keyArray[1] ?? '';
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
        $outputFile = str_replace('{server}', $this->server, $outputFile);
        file_put_contents($outputFile, json_encode($this->swagger, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->logger->debug('Generate swagger.json success!');
    }
}
