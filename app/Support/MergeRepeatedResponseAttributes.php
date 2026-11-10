<?php

namespace App\Support;

use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Dedoc\Scramble\Attributes\WithRelations;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Infer\Services\FileNameResolver;
use Dedoc\Scramble\Support\Generator\Combined\AnyOf;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;
use ReflectionAttribute;

use function DeepCopy\deep_copy;

/**
 * Scramble's ResponseExtension applies each #[Response] attribute over the
 * previous one of the same status, so an action documenting several 409s
 * ended up with only the last. This runs after it and rebuilds every status
 * that more than one attribute documents as one response: the description
 * lists each attribute's description as a bullet, and the schemas merge into
 * one when they share a shape (a property whose parts are all literals, such
 * as `error_code: 'slot_not_empty'`, becomes an enum of those values).
 * Statuses documented once are left exactly as Scramble built them.
 */
class MergeRepeatedResponseAttributes extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $action = $routeInfo->reflectionAction();
        if ($action === null) {
            return;
        }

        $groups = [];
        foreach ($action->getAttributes(ResponseAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $instance = $attribute->newInstance();
            $groups[(string) $instance->status][] = $instance;
        }

        $groups = array_filter($groups, fn (array $attributes): bool => count($attributes) > 1);
        if ($groups === []) {
            return;
        }

        $fileName = $action->getFileName();
        $nameResolver = is_string($fileName) ? FileNameResolver::createForFile($fileName) : null;
        $withRelations = array_map(
            fn (ReflectionAttribute $attribute): WithRelations => $attribute->newInstance(),
            $action->getAttributes(WithRelations::class, ReflectionAttribute::IS_INSTANCEOF),
        );

        foreach ($groups as $status => $attributes) {
            $parts = array_map(fn (ResponseAttribute $attribute): Response => ResponseAttribute::toOpenApiResponse(
                $attribute,
                null,
                $this->openApiTransformer,
                $nameResolver,
                $withRelations,
                $this->infer->index,
            ), $attributes);

            $this->replaceResponse($operation, (string) $status, $this->merge((string) $status, $parts, $operation));
        }
    }

    /**
     * @param  list<Response>  $parts
     */
    private function merge(string $status, array $parts, Operation $operation): Response
    {
        $existing = $this->findResponse($operation, $status);
        $merged = $existing ? deep_copy($existing) : Response::make($parts[0]->code);

        $merged->setDescription(implode("\n", array_map(
            fn (Response $part): string => '- '.trim($part->description),
            $parts,
        )));

        $mediaTypes = array_unique(array_merge(...array_map(fn (Response $part): array => array_keys($part->content), $parts)));
        foreach ($mediaTypes as $mediaType) {
            $types = [];
            foreach ($parts as $part) {
                if (isset($part->content[$mediaType])) {
                    $schema = $part->content[$mediaType];
                    $types[] = $schema instanceof Reference ? $schema->resolve()->type : $schema->type;
                }
            }

            $merged->setContent($mediaType, Schema::fromType($this->mergeTypes($types)));
        }

        return $merged;
    }

    /**
     * @param  list<Type>  $types
     */
    private function mergeTypes(array $types): Type
    {
        $objects = array_filter($types, fn (Type $type): bool => $type instanceof ObjectType);
        if (count($objects) === count($types) && ($object = $this->mergeObjects(array_values($objects))) !== null) {
            return $object;
        }

        $distinct = [];
        foreach ($types as $type) {
            $distinct[json_encode($type->toArray(), JSON_THROW_ON_ERROR)] = $type;
        }

        return count($distinct) === 1 ? reset($distinct) : (new AnyOf)->setItems(array_values($distinct));
    }

    /**
     * Objects with the same properties and required keys merge into one; a
     * property whose parts differ only by a literal value becomes their enum.
     *
     * @param  list<ObjectType>  $objects
     */
    private function mergeObjects(array $objects): ?ObjectType
    {
        $first = $objects[0];
        $keys = array_keys($first->properties);
        foreach ($objects as $object) {
            if (array_keys($object->properties) !== $keys || $object->required !== $first->required) {
                return null;
            }
        }

        $merged = $first->clone();
        foreach ($keys as $key) {
            $property = $this->mergeProperty(array_map(fn (ObjectType $object): Type => $object->properties[$key], $objects));
            if ($property === null) {
                return null;
            }
            $merged->addProperty($key, $property);
        }

        return $merged;
    }

    /**
     * @param  list<Type>  $types
     */
    private function mergeProperty(array $types): ?Type
    {
        $values = [];
        $shapes = [];
        foreach ($types as $type) {
            $literals = $type->const !== null ? [$type->const] : $type->enum;
            $values = $literals === [] || $values === null ? null : [...$values, ...$literals];
            $shape = $type->clone()->enum([]);
            $shape->const = null;
            $shapes[json_encode($shape->toArray(), JSON_THROW_ON_ERROR)] = $shape;
        }

        if (count($shapes) !== 1) {
            return null;
        }

        $shape = reset($shapes);

        // When any part is open (e.g. a plain string), so is the union.
        return $values === null ? $shape : $shape->enum(array_values(array_unique($values)));
    }

    private function findResponse(Operation $operation, string $status): ?Response
    {
        foreach ($operation->responses ?? [] as $response) {
            $resolved = $response instanceof Reference ? $response->resolve() : $response;
            if ($resolved instanceof Response && (string) $resolved->code === $status) {
                return $resolved;
            }
        }

        return null;
    }

    private function replaceResponse(Operation $operation, string $status, Response $merged): void
    {
        $responses = [];
        $placed = false;
        foreach ($operation->responses ?? [] as $response) {
            $resolved = $response instanceof Reference ? $response->resolve() : $response;
            if ($resolved instanceof Response && (string) $resolved->code === $status) {
                if (! $placed) {
                    $responses[] = $merged;
                    $placed = true;
                }

                continue;
            }
            $responses[] = $response;
        }

        if (! $placed) {
            $responses[] = $merged;
        }

        $operation->responses = $responses;
    }
}
