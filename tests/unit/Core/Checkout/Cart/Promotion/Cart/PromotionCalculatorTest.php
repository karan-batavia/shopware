<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\CartWeightRule;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionBuilder;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackagePicker;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\PackageFilter;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\SetGroupScopeFilter;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionExcludedError;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotEligibleError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCalculator;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Content\Flow\Rule\OrderCreatedByAdminRule;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(PromotionCalculator::class)]
class PromotionCalculatorTest extends TestCase
{
    private IdsCollection $ids;

    private PromotionCalculator $promotionCalculator;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $order = new OrderEntity();
        $order->setId($this->ids->get('order-1'));

        $collection = new OrderCollection([$order]);

        /** @var StaticEntityRepository<OrderCollection> $repository */
        $repository = new StaticEntityRepository([
            $collection,
            $collection,
        ]);

        $this->promotionCalculator = new PromotionCalculator(
            $this->createMock(AmountCalculator::class),
            $this->createMock(AbsolutePriceCalculator::class),
            $this->createMock(LineItemGroupBuilder::class),
            $this->createMock(DiscountCompositionBuilder::class),
            $this->createMock(PackageFilter::class),
            $this->createMock(AdvancedPackagePicker::class),
            $this->createMock(SetGroupScopeFilter::class),
            $this->createMock(LineItemQuantitySplitter::class),
            $this->createMock(PercentagePriceCalculator::class),
            $this->createMock(DiscountPackager::class),
            $this->createMock(DiscountPackager::class),
            $this->createMock(DiscountPackager::class),
            $repository,
        );
    }

    public function testLineItemWithoutRequirement(): void
    {
        $cart = $this->createCartWithLineItem();
        $promotion = $this->createPromotion();

        $this->promotionCalculator->calculate(
            new LineItemCollection([$promotion]),
            $cart,
            $cart,
            $this->createMock(SalesChannelContext::class),
            new CartBehavior()
        );

        static::assertCount(0, $cart->getErrors());
    }

    public function testBuildScopeWithRootFlowRule(): void
    {
        /** @phpstan-ignore shopware.mockingSimpleObjects */
        $rule = $this->createMock(OrderCreatedByAdminRule::class);
        $rule->expects(static::once())
            ->method('match')
            ->with(static::isInstanceOf(CartRuleScope::class))
            ->willReturn(false);

        $promotion = $this->createPromotion($rule);
        $cart = $this->createCartWithLineItem();
        $cart->addExtension(
            OrderConverter::ORIGINAL_ID,
            new IdStruct($this->ids->get('order-1'))
        );

        $this->promotionCalculator->calculate(
            new LineItemCollection([$promotion]),
            $cart,
            $cart,
            $this->createMock(SalesChannelContext::class),
            new CartBehavior()
        );

        static::assertCount(1, $cart->getErrors());
        static::assertInstanceOf(PromotionNotEligibleError::class, $cart->getErrors()->first());
    }

    public function testBuildScopeWithRootNonFlowRule(): void
    {
        /** @phpstan-ignore shopware.mockingSimpleObjects */
        $rule = $this->createMock(CartWeightRule::class);
        $rule->expects(static::once())
            ->method('match')
            ->with(static::isInstanceOf(CartRuleScope::class))
            ->willReturn(false);

        $promotion = $this->createPromotion($rule);
        $cart = $this->createCartWithLineItem();
        $cart->addExtension(
            OrderConverter::ORIGINAL_ID,
            new IdStruct($this->ids->get('order-1'))
        );

        $this->promotionCalculator->calculate(
            new LineItemCollection([$promotion]),
            $cart,
            $cart,
            $this->createMock(SalesChannelContext::class),
            new CartBehavior()
        );

        // not eligible cause match result is false
        static::assertCount(1, $cart->getErrors());
        static::assertInstanceOf(PromotionNotEligibleError::class, $cart->getErrors()->first());
    }

    public function testBuildScopeWithRuleContainer(): void
    {
        /** @phpstan-ignore shopware.mockingSimpleObjects */
        $rule = $this->createMock(CartWeightRule::class);
        $rule->expects(static::once())
            ->method('match')
            ->with(static::isInstanceOf(CartRuleScope::class))
            ->willReturn(false);

        $container = new AndRule([new OrRule([$rule])]);

        $promotion = $this->createPromotion($container);
        $cart = $this->createCartWithLineItem();
        $cart->addExtension(
            OrderConverter::ORIGINAL_ID,
            new IdStruct($this->ids->get('order-1'))
        );

        $this->promotionCalculator->calculate(
            new LineItemCollection([$promotion]),
            $cart,
            $cart,
            $this->createMock(SalesChannelContext::class),
            new CartBehavior()
        );

        // not eligible cause match result is false
        static::assertCount(1, $cart->getErrors());
        static::assertInstanceOf(PromotionNotEligibleError::class, $cart->getErrors()->first());
    }

    public function testBuildScopeWhenOrderIdCanNotBeFoundInCart(): void
    {
        /** @phpstan-ignore shopware.mockingSimpleObjects */
        $rule = $this->createMock(OrderCreatedByAdminRule::class);
        $rule->expects(static::never())->method('match');

        $container = new AndRule([new OrRule([$rule])]);

        $promotion = $this->createPromotion($container);
        $cart = $this->createCartWithLineItem();

        $this->promotionCalculator->calculate(
            new LineItemCollection([$promotion]),
            $cart,
            $cart,
            $this->createMock(SalesChannelContext::class),
            new CartBehavior()
        );

        // not eligible cause match result is false
        static::assertCount(1, $cart->getErrors());
        static::assertInstanceOf(PromotionNotEligibleError::class, $cart->getErrors()->first());
    }

    public function testBuildScopeWhenOrderCanNotBeFound(): void
    {
        /** @phpstan-ignore shopware.mockingSimpleObjects */
        $rule = $this->createMock(OrderCreatedByAdminRule::class);
        $rule->expects(static::never())->method('match');

        $container = new AndRule([new OrRule([$rule])]);

        $promotion = $this->createPromotion($container);
        $cart = $this->createCartWithLineItem();
        $cart->addExtension(
            OrderConverter::ORIGINAL_ID,
            new IdStruct($this->ids->get('order-1'))
        );

        (new PromotionCalculator(
            $this->createMock(AmountCalculator::class),
            $this->createMock(AbsolutePriceCalculator::class),
            $this->createMock(LineItemGroupBuilder::class),
            $this->createMock(DiscountCompositionBuilder::class),
            $this->createMock(PackageFilter::class),
            $this->createMock(AdvancedPackagePicker::class),
            $this->createMock(SetGroupScopeFilter::class),
            $this->createMock(LineItemQuantitySplitter::class),
            $this->createMock(PercentagePriceCalculator::class),
            $this->createMock(DiscountPackager::class),
            $this->createMock(DiscountPackager::class),
            $this->createMock(DiscountPackager::class),
            $this->createMock(EntityRepository::class),
        ))->calculate(
            new LineItemCollection([$promotion]),
            $cart,
            $cart,
            $this->createMock(SalesChannelContext::class),
            new CartBehavior()
        );

        // not eligible cause match result is false
        static::assertCount(1, $cart->getErrors());
        static::assertInstanceOf(PromotionNotEligibleError::class, $cart->getErrors()->first());
    }

    public function testBuildScopeWhenOrderCanBeFound(): void
    {
        /** @phpstan-ignore shopware.mockingSimpleObjects */
        $rule = $this->createMock(OrderCreatedByAdminRule::class);
        $rule->expects(static::once())
            ->method('match')
            ->with(static::isInstanceOf(FlowRuleScope::class))
            ->willReturn(true);

        $container = new AndRule([new OrRule([$rule])]);

        $promotion = $this->createPromotion($container);
        $cart = $this->createCartWithLineItem();
        $cart->addExtension(
            OrderConverter::ORIGINAL_ID,
            new IdStruct($this->ids->get('order-1'))
        );

        $this->promotionCalculator->calculate(
            new LineItemCollection([$promotion]),
            $cart,
            $cart,
            $this->createMock(SalesChannelContext::class),
            new CartBehavior()
        );

        static::assertCount(0, $cart->getErrors());
    }

    public function testPromotionPrioritySorting(): void
    {
        $lineItems = new LineItem($this->ids->get('line-item-1'), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItems->setPriceDefinition(new AbsolutePriceDefinition(50.0));
        $lineItems->setLabel('Product');

        $firstDiscountItem = $this->getDiscountItem('frist-promotion')
            ->setPayloadValue('code', 'code-1')
            ->setPayloadValue('exclusions', ['second-promotion'])
            ->setPayloadValue('priority', 2);

        $secondDiscountItem = $this->getDiscountItem('second-promotion')
            ->setPayloadValue('code', 'code-2')
            ->setPayloadValue('exclusions', ['frist-promotion'])
            ->setPayloadValue('priority', 1)
            ->setPriceDefinition(new AbsolutePriceDefinition(-20.0));

        $cart = new Cart('promotion-test');
        $cart->addLineItems(new LineItemCollection([$lineItems]));

        $this->promotionCalculator->calculate(
            new LineItemCollection([$secondDiscountItem, $firstDiscountItem]),
            $cart,
            $cart,
            $this->createMock(SalesChannelContext::class),
            new CartBehavior()
        );

        static::assertCount(1, $cart->getErrors());
        $error = $cart->getErrors()->first();

        static::assertInstanceOf(PromotionExcludedError::class, $error);
        static::assertEquals('Promotion second-promotion was excluded for cart.', $error->getMessage());
    }

    private function createCartWithLineItem(): Cart
    {
        $lineItem = new LineItem($this->ids->get('line-item-1'), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setLabel('Product');

        $cart = new Cart('promotion');
        $cart->addLineItems(new LineItemCollection([$lineItem]));

        return $cart;
    }

    private function createPromotion(?Rule $requirement = null): LineItem
    {
        $promotion = new LineItem($this->ids->get('line-item-1'), PromotionProcessor::LINE_ITEM_TYPE);
        $promotion->setPayloadValue('code', 'code-1');
        $promotion->setPayloadValue('priority', 100);
        $promotion->setPayloadValue('exclusions', []);
        $promotion->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_CART);
        $promotion->setPayloadValue('discountType', PromotionDiscountEntity::TYPE_ABSOLUTE);
        $promotion->setPayloadValue('promotionId', 'code-1');
        $promotion->setReferencedId('code-1');
        $promotion->setLabel('Discount');
        $promotion->setPriceDefinition(new AbsolutePriceDefinition(50.0));

        if ($requirement !== null) {
            $promotion->setRequirement($requirement);
        }

        return $promotion;
    }

    private function getDiscountItem(string $promotionId): LineItem
    {
        $discountItemToBeExcluded = new LineItem($promotionId, PromotionProcessor::LINE_ITEM_TYPE);
        $discountItemToBeExcluded->setRequirement(null);
        $discountItemToBeExcluded->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_CART);
        $discountItemToBeExcluded->setPayloadValue('discountType', PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discountItemToBeExcluded->setPayloadValue('exclusions', []);
        $discountItemToBeExcluded->setPayloadValue('promotionId', $promotionId);
        $discountItemToBeExcluded->setReferencedId($promotionId);
        $discountItemToBeExcluded->setLabel('Discount');
        $discountItemToBeExcluded->setPriceDefinition(new AbsolutePriceDefinition(-10.0));

        return $discountItemToBeExcluded;
    }
}
