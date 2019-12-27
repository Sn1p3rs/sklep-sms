<?php
namespace Tests\Feature\Http\Api\Admin;

use App\Repositories\ServiceRepository;
use App\Services\ExtraFlags\ExtraFlagType;
use App\Services\ExtraFlags\ServiceExtraFlags;
use Tests\Psr4\TestCases\HttpTestCase;

class ServiceCollectionTest extends HttpTestCase
{
    /** @test */
    public function creates_service()
    {
        // given
        /** @var ServiceRepository $repository */
        $repository = $this->app->make(ServiceRepository::class);
        $admin = $this->factory->admin();
        $this->actAs($admin);

        // when
        $response = $this->post("/api/admin/services", [
            'id' => 'example',
            'name' => 'My Example',
            'module' => ServiceExtraFlags::MODULE_ID,
            'order' => 1,
            'web' => 1,
            'flags' => 'a',
            'type' => [ExtraFlagType::TYPE_NICK],
        ]);

        // then
        $this->assertSame(200, $response->getStatusCode());
        $json = $this->decodeJsonResponse($response);
        $this->assertSame("ok", $json["return_id"]);
        $service = $repository->get("example");
        $this->assertSame("My Example", $service->getName());
        $this->assertSame(ServiceExtraFlags::MODULE_ID, $service->getModule());
    }

    /** @test */
    public function cannot_use_the_same_id_twice()
    {
        // given
        /** @var ServiceRepository $repository */
        $repository = $this->app->make(ServiceRepository::class);
        $admin = $this->factory->admin();
        $this->actAs($admin);

        $id = 'example';
        $repository->create($id, "a", "b", "c", "d", ServiceExtraFlags::MODULE_ID, [], 1);

        // when
        $response = $this->post("/api/admin/services", [
            'id' => $id,
            'name' => 'My Example',
            'module' => ServiceExtraFlags::MODULE_ID,
            'order' => 1,
            'web' => 1,
            'flags' => 'a',
            'type' => [ExtraFlagType::TYPE_NICK],
        ]);

        // then
        $this->assertSame(200, $response->getStatusCode());
        $json = $this->decodeJsonResponse($response);
        $this->assertSame("error", $json["return_id"]);
    }
}