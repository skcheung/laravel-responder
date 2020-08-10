<?php

namespace Flugg\Responder\Tests\Unit\Http\Normalizers;

use Flugg\Responder\Http\Normalizers\ResourceNormalizer;
use Flugg\Responder\Http\SuccessResponse;
use Flugg\Responder\Tests\UnitTestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Unit tests for the [Flugg\Responder\Http\Normalizers\ResourceNormalizer] class.
 *
 * @see \Flugg\Responder\Http\Normalizers\ResourceNormalizer
 */
class ResourceNormalizerTest extends UnitTestCase
{
    /**
     * Mock of a request.
     *
     * @var \Mockery\MockInterface|\Illuminate\Http\Request
     */
    protected $request;

    /**
     * Mock of a JSON response.
     *
     * @var \Mockery\MockInterface|\Illuminate\Http\JsonResponse
     */
    protected $response;

    /**
     * Mock of a JSON resource.
     *
     * @var \Mockery\MockInterface|\Illuminate\Http\Resources\Json\JsonResource
     */
    protected $resource;

    /**
     * Class being tested.
     *
     * @var \Flugg\Responder\Http\Normalizers\ResourceNormalizer
     */
    protected $normalizer;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->request = mock(Request::class);
        $this->response = mock(Response::class);
        $this->resource = mock(JsonResource::class);
        $this->resource->allows('response')->andReturn($this->response);
        $this->resource->additional = [];
        $this->normalizer = new ResourceNormalizer($this->request);
    }

    /**
     * Assert that [normalize] normalizes API resources to a success response value object.
     */
    public function testNormalizeMethodNormalizesResource()
    {
        $this->resource->allows([
            'resolve' => $data = [1, 2, 3],
            'toArray' => $data,
            'with' => $meta = ['foo' => ['bar' => 123]]
        ]);
        $this->resource->additional = $additional = ['foo' => ['baz' => 123]];
        $this->response->allows('status')->andReturns($status = 200);
        $this->response->headers = $bag = mock(ResponseHeaderBag::class);
        $bag->allows('all')->andReturn($headers = ['x-foo' => 123]);

        $result = $this->normalizer->normalize($this->resource);

        $this->assertInstanceOf(SuccessResponse::class, $result);
        $this->assertSame($data, $result->resource()->data());
        $this->assertSame($status, $result->status());
        $this->assertSame($headers, $result->headers());
        $this->assertSame(array_merge_recursive($meta, $additional), $result->meta());
        $this->resource->shouldHaveReceived('with')->with($this->request);
    }

    /**
     * Assert that [normalize] normalizes API resources with relationships.
     */
    public function testNormalizeMethodNormalizesNestedResources()
    {
        $this->resource->allows([
            'resolve' => [
                'id' => 1,
                'foo' => ['id' => 2, 'bar' => [
                    'bar' => ['id' => 3]
                ]],
                'baz' => [['id' => 4], ['id' => 5]]
            ],
            'toArray' => [
                'id' => 1,
                'foo' => $fooResource = mock(JsonResource::class),
                'baz' => $bazResource = mock(ResourceCollection::class)
            ],
            'with' => []
        ]);
        $fooResource->allows([
            'resolve' => ['id' => 2],
            'toArray' => ['id' => 2, 'bar' => $barResource = mock(JsonResource::class)],
        ]);
        $barResource->allows([
            'resolve' => ['id' => 3],
            'toArray' => ['id' => 3],
        ]);
        $bazResource->allows([
            'resolve' => [['id' => 4], ['id' => 5]],
            'toArray' => [['id' => 4], ['id' => 5]],
        ]);
        $this->response->allows('status')->andReturns(200);
        $this->response->headers = $bag = mock(ResponseHeaderBag::class);
        $bag->allows('all')->andReturn([]);

        $result = $this->normalizer->normalize($this->resource);

        $this->assertSame(['id' => 1], $result->resource()->data());
        $this->assertSame(['id' => 2], $result->resource()->relations()[0]->data());
        $this->assertSame(['id' => 3], $result->resource()->relations()[0]->relations()[0]->data());
        $this->assertSame([['id' => 4], ['id' => 5]], $result->resource()->relations()[1]->data());
    }
}