<?php
namespace Tests\Feature\Http\View\Admin;

use Tests\Psr4\Concerns\AuthConcern;
use Tests\Psr4\Concerns\MakePurchaseConcern;
use Tests\Psr4\TestCases\HttpTestCase;

class IncomeTest extends HttpTestCase
{
    use AuthConcern;
    use MakePurchaseConcern;

    protected function setUp()
    {
        parent::setUp();
        $this->createRandomPurchase();
    }

    /** @test */
    public function it_loads()
    {
        // given
        $this->actingAs($this->factory->admin());

        // when
        $response = $this->get('/admin/income');

        // then
        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('Panel Admina', $response->getContent());
        $this->assertContains('PA: Przychód - Sklep SMS', $response->getContent());
    }
}
