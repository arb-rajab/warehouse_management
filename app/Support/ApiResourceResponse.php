<?php

namespace App\Support;

use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\PaginatedResourceResponseTypeToSchema;
use Dedoc\Scramble\Support\TypeToSchemaExtensions\ResourceResponseTypeToSchema;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\PaginatedResourceResponse;

/**
 * Apply WrapStoreApiResources semantics while inferring documentation only.
 */
class ApiResourceResponse extends ResourceResponseTypeToSchema
{
    public function shouldHandle(Type $type): bool
    {
        return parent::shouldHandle($type)
            && ($resource = $type->templateTypes[0] ?? null) instanceof ObjectType
            && str_starts_with($resource->name, 'App\\Http\\Resources\\V2\\');
    }

    public function toResponse(Type $type): Response
    {
        $previousWrapper = JsonResource::$wrap;
        JsonResource::wrap('data');

        try {
            $paginated = new class($this->infer, $this->openApiTransformer, $this->components, $this->openApiContext) extends PaginatedResourceResponseTypeToSchema
            {
                protected function wrapper(Type $resource): string
                {
                    return 'data';
                }
            };

            return $type->isInstanceOf(PaginatedResourceResponse::class)
                ? $paginated->toResponse($type)
                : parent::toResponse($type);
        } finally {
            JsonResource::$wrap = $previousWrapper;
        }
    }
}
