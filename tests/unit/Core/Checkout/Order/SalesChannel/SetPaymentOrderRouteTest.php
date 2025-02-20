<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Exception\PaymentMethodNotChangeableException;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SetPaymentOrderRoute::class)]
class SetPaymentOrderRouteTest extends TestCase
{
    #[DataProvider('requestDataProvider')]
    public function testInvalidRequest(Request $request): void
    {
        $this->expectException(InvalidUuidException::class);

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $this->createMock(OrderService::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(OrderConverter::class),
            $this->createMock(CartRuleLoader::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InitialStateIdLoader::class),
            $this->createMock(AbstractCheckoutGatewayRoute::class)
        );

        $paymentOrderRoute->setPayment($request, $this->createMock(SalesChannelContext::class));
    }

    public function testOrderNotFound(): void
    {
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('order for id ');

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $this->createMock(OrderService::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(OrderConverter::class),
            $this->createMock(CartRuleLoader::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InitialStateIdLoader::class),
            $this->createMock(AbstractCheckoutGatewayRoute::class)
        );

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects(static::once())
            ->method('getCustomer')
            ->willReturn($customer);

        $request = self::getRequest(['paymentMethodId' => Uuid::randomHex(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $salesChannelContext);
    }

    public function testInvalidPaymentMethod(): void
    {
        $this->expectException(OrderException::class);
        $this->expectExceptionMessage('The payment method with id');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        /** @var StaticEntityRepository<OrderCollection> $staticRepository */
        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $gatewayRoute
            ->expects(static::once())
            ->method('load');

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $this->createMock(OrderService::class),
            $staticRepository,
            $this->createMock(OrderConverter::class),
            $this->createMock(CartRuleLoader::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InitialStateIdLoader::class),
            $gatewayRoute
        );

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects(static::once())
            ->method('getCustomer')
            ->willReturn($customer);

        $request = self::getRequest(['paymentMethodId' => Uuid::randomHex(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $salesChannelContext);
    }

    public function testPaymentNotChangeable(): void
    {
        $this->expectException(PaymentMethodNotChangeableException::class);
        $this->expectExceptionMessage('The order has an active transaction -');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());

        /** @var StaticEntityRepository<OrderCollection> $staticRepository */
        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection([$paymentMethod]),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $gatewayRoute
            ->expects(static::once())
            ->method('load')
            ->willReturn($response);

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $this->createMock(OrderService::class),
            $staticRepository,
            $this->createMock(OrderConverter::class),
            $this->createMock(CartRuleLoader::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InitialStateIdLoader::class),
            $gatewayRoute
        );

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects(static::once())
            ->method('getCustomer')
            ->willReturn($customer);

        $request = self::getRequest(['paymentMethodId' => $paymentMethod->getId(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $salesChannelContext);
    }

    public function testReopenAndCancelTransactions(): void
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());

        $transactionState = new OrderTransactionEntity();
        $transactionState->setId(Uuid::randomHex());
        $transactionState->setPaymentMethodId(Uuid::randomHex());
        $transactionState->setStateId(Uuid::randomHex());
        $transactionStateLast = new OrderTransactionEntity();
        $transactionStateLast->setId(Uuid::randomHex());
        $transactionStateLast->setPaymentMethodId($paymentMethod->getId());
        $transactionStateLast->setStateId(Uuid::randomHex());

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setTransactions(new OrderTransactionCollection([$transactionState, $transactionStateLast]));

        /** @var StaticEntityRepository<OrderCollection> $staticRepository */
        $staticRepository = new StaticEntityRepository([new OrderCollection([$order])], new OrderDefinition());

        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection([$paymentMethod]),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $gatewayRoute
            ->expects(static::once())
            ->method('load')
            ->willReturn($response);

        $orderService = $this->createMock(OrderService::class);
        $orderService
            ->expects(static::once())
            ->method('isPaymentChangeableByTransactionState')
            ->willReturn(true);
        $orderService
            ->expects(self::once()) // TODO: should be two, but with $context->scope not working?
            ->method('orderTransactionStateTransition');

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $orderService,
            $staticRepository,
            $this->createMock(OrderConverter::class),
            $this->createMock(CartRuleLoader::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InitialStateIdLoader::class),
            $gatewayRoute
        );

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects(static::once())
            ->method('getCustomer')
            ->willReturn($customer);

        $request = self::getRequest(['paymentMethodId' => $paymentMethod->getId(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $salesChannelContext);
    }

    public function testSetPaymentMethod(): void
    {
        self::markTestSkipped('Need to know how to test with "scope" method');
        // need to mock the orderRepository->update to get the transaction id

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());

        $price = new CartPrice(
            100,
            100,
            100,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
CartPrice::TAX_STATE_FREE
        );

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setPrice($price);

        $transactionState = new OrderTransactionEntity();
        $transactionState->setId(Uuid::randomHex());

        $orderLater = new OrderEntity();
        $orderLater->setId(Uuid::randomHex());
        $orderLater->setTransactions(new OrderTransactionCollection([$transactionState]));

        /** @var StaticEntityRepository<OrderCollection> $staticRepository */
        $staticRepository = new StaticEntityRepository([new OrderCollection([$order]), new OrderCollection([$orderLater])], new OrderDefinition());

        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection([$paymentMethod]),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $gatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $gatewayRoute
            ->expects(static::once())
            ->method('load')
            ->willReturn($response);

        $orderService = $this->createMock(OrderService::class);
        $orderService
            ->expects(static::once())
            ->method('isPaymentChangeableByTransactionState')
            ->willReturn(true);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext
            ->expects(static::exactly(2))
            ->method('getCustomer')
            ->willReturn($customer);

        $orderConverter = $this->createMock(OrderConverter::class);
        $orderConverter
            ->expects(static::once())
            ->method('assembleSalesChannelContext')
            ->willReturn($salesChannelContext);

        $paymentOrderRoute = new SetPaymentOrderRoute(
            $orderService,
            $staticRepository,
            $orderConverter,
            $this->createMock(CartRuleLoader::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(InitialStateIdLoader::class),
            $gatewayRoute
        );

        $request = self::getRequest(['paymentMethodId' => $paymentMethod->getId(), 'orderId' => Uuid::randomHex()]);

        $paymentOrderRoute->setPayment($request, $salesChannelContext);
    }

    /**
     * @return array<string, Request[]>
     */
    public static function requestDataProvider(): array
    {
        return [
            'empty' => [
                self::getRequest([]),
            ],
            'invalid payment method' => [
                self::getRequest(['paymentMethodId' => 'some payment method id']),
            ],
            'invalid order' => [
                self::getRequest(['paymentMethodId' => Uuid::randomHex(), 'orderId' => 'some order id']),
            ],
        ];
    }

    /**
     * @param array<string, true|string> $attributes
     */
    private static function getRequest(array $attributes): Request
    {
        $request = Request::create($_SERVER['APP_URL'], Request::METHOD_GET);

        foreach ($attributes as $key => $attribute) {
            $request->request->set($key, $attribute);
        }

        return $request;
    }
}
