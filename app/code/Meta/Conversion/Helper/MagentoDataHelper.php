<?php
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Meta\Conversion\Helper;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use Meta\Catalog\Helper\Product\Identifier as ProductIdentifier;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Customer\Model\AddressFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Customer\Model\Address;
use Magento\Customer\Model\Customer;

/**
 * Helper class to get data using Magento Platform methods.
 *
 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class MagentoDataHelper extends AbstractHelper
{
    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var CustomerMetadataInterface
     */
    private CustomerMetadataInterface $customerMetadata;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var ProductIdentifier
     */
    private ProductIdentifier $productIdentifier;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @var Quote|null
     */
    private $quote;

    /**
     * @var CustomerSession
     */
    private CustomerSession $customerSession;

    /**
     * @var CategoryRepositoryInterface
     */
    private CategoryRepositoryInterface $categoryRepository;

    /**
     * @var PricingHelper
     */
    private PricingHelper $pricingHelper;

    /**
     * @var AddressFactory
     */
    private AddressFactory $addressFactory;

    /**
     * @var RegionFactory
     */
    private RegionFactory $regionFactory;

    /**
     * MagentoDataHelper constructor
     *
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param CustomerMetadataInterface $customerMetadata
     * @param ProductRepositoryInterface $productRepository
     * @param ProductIdentifier $productIdentifier
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param CategoryRepositoryInterface $categoryRepository
     * @param PricingHelper $pricingHelper
     * @param AddressFactory $addressFactory
     * @param RegionFactory $regionFactory
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        CustomerMetadataInterface $customerMetadata,
        ProductRepositoryInterface $productRepository,
        ProductIdentifier $productIdentifier,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        CategoryRepositoryInterface $categoryRepository,
        PricingHelper $pricingHelper,
        AddressFactory $addressFactory,
        RegionFactory $regionFactory
    ) {
        parent::__construct($context);
        $this->storeManager = $storeManager;
        $this->customerMetadata = $customerMetadata;
        $this->productRepository = $productRepository;
        $this->productIdentifier = $productIdentifier;
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->categoryRepository = $categoryRepository;
        $this->pricingHelper = $pricingHelper;
        $this->addressFactory = $addressFactory;
        $this->regionFactory = $regionFactory;
    }

    /**
     * Return currently logged in users's email.
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->customerSession->getCustomer()->getEmail();
    }

    /**
     * Return currently logged in users' First Name.
     *
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->customerSession->getCustomer()->getFirstname();
    }

    /**
     * Return currently logged in users' Last Name.
     *
     * @return string
     */
    public function getLastName(): string
    {
        return $this->customerSession->getCustomer()->getLastname();
    }

    /**
     * Return the product by the given sku
     *
     * @param string $productSku
     * @return ProductInterface | bool
     */
    public function getProductBySku(string $productSku)
    {
        try {
            return $this->productRepository->get($productSku);
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }

    /**
     * Get Product by ID
     *
     * @param mixed $productId
     * @return false|ProductInterface
     */
    public function getProductById($productId)
    {
        try {
            return $this->productRepository->getById($productId);
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }

    /**
     * Return the categories for the given product
     *
     * @param Product $product
     * @return string
     */
    public function getCategoriesForProduct($product): string
    {
        $categoryIds = $product->getCategoryIds();
        if (count($categoryIds) > 0) {
            $categoryNames = [];
            foreach ($categoryIds as $categoryId) {
                try {
                    $category = $this->categoryRepository->get($categoryId);
                } catch (NoSuchEntityException $e) {
                    continue;
                }
                $categoryNames[] = $category->getName();
            }
            return addslashes(implode(',', $categoryNames)); // phpcs:ignore
        }

        return '';
    }

    /**
     * Get content type
     *
     * @param Product $product
     * @return string
     */
    public function getContentType(Product $product): string
    {
        return $product->getTypeId() == Configurable::TYPE_CODE ? 'product_group' : 'product';
    }

    /**
     * Get Content IDs (Product IDs)
     *
     * @param Product $product
     * @return bool|int|string
     */
    public function getContentId(Product $product)
    {
        return $this->productIdentifier->getContentId($product);
    }

    /**
     * Return the price for the given product
     *
     * @param Product $product
     * @return float
     */
    public function getValueForProduct(Product $product): float
    {
        $price = $product->getFinalPrice();
        return $this->pricingHelper->currency($price, false, false);
    }

    /**
     * Return the currency used in the store
     *
     * @return string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCurrency(): string
    {
        return $this->storeManager->getStore()->getCurrentCurrency()->getCode();
    }

    /**
     * Return the ids of the items added to the cart
     *
     * @return array
     */
    public function getCartContentIds(): array
    {
        $contentIds = [];
        if (!$this->getQuote()) {
            return [];
        }
        $items = $this->getQuote()->getAllVisibleItems();
        foreach ($items as $item) {
            $contentIds[] = $this->getContentId($item->getProduct());
        }
        return $contentIds;
    }

    /**
     * Return the cart total value
     *
     * @return float|null
     */
    public function getCartTotal(): ?float
    {
        if (!$this->getQuote()) {
            return null;
        }
        $subtotal = $this->getQuote()->getSubtotal();
        if ($subtotal) {
            return $this->pricingHelper->currency($subtotal, false, false);
        } else {
            return null;
        }
    }

    /**
     * Return the amount of items in the cart
     *
     * @return int
     */
    public function getCartNumItems(): int
    {
        $numItems = 0;
        if (!$this->getQuote()) {
            return $numItems;
        }
        $items = $this->getQuote()->getAllVisibleItems();
        foreach ($items as $item) {
            $numItems += $item->getQty();
        }
        return $numItems;
    }

    /**
     * Return information about the cart items
     *
     * @link https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/custom-data/#contents
     *
     * @return array
     */
    public function getCartContents(): array
    {
        if (!$this->getQuote()) {
            return [];
        }
        $contents = [];
        $items = $this->getQuote()->getAllVisibleItems();

        foreach ($items as $item) {
            $product = $item->getProduct();
            $contents[] = [
                'product_id' => $this->getContentId($product),
                'quantity' => (int) $item->getQty(),
            ];
        }
        return $contents;
    }

    /**
     * Return the ids of the items in the last order
     *
     * @return array
     */
    public function getOrderContentIds(): array
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order) {
            return [];
        }
        $contentIds = [];
        $items = $order->getAllVisibleItems();
        foreach ($items as $item) {
            $contentIds[] = $this->getContentId($item->getProduct());
        }
        return $contentIds;
    }

    /**
     * Return the last order total value
     *
     * @return float|null
     */
    public function getOrderTotal(): ?float
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order) {
            return null;
        }
        $subtotal = $order->getSubTotal();
        if ($subtotal) {
            return $this->pricingHelper->currency($subtotal, false, false);
        } else {
            return null;
        }
    }

    /**
     * Return information about the last order items
     *
     * @link https://developers.facebook.com/docs/marketing-api/conversions-api/parameters/custom-data/#contents
     *
     * @return array
     */
    public function getOrderContents(): array
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order) {
            return [];
        }
        $contents = [];
        $items = $order->getAllVisibleItems();
        foreach ($items as $item) {
            $product = $item->getProduct();
            $contents[] = [
                'product_id' => $this->getContentId($product),
                'quantity' => (int)$item->getQtyOrdered()
            ];
        }
        return $contents;
    }

    /**
     * Return the id of the last order
     *
     * @return mixed|null
     */
    public function getOrderId()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order) {
            return null;
        } else {
            return $order->getId();
        }
    }

    /**
     * Return an object representing the current logged in customer
     *
     * @return Customer|null
     */
    public function getCurrentCustomer(): ?Customer
    {
        if (!$this->customerSession->isLoggedIn()) {
            return null;
        }

        return $this->customerSession->getCustomer();
    }

    /**
     * Return the address of a given customer
     *
     * @param mixed $customer
     * @return Address
     */
    public function getCustomerAddress($customer): Address
    {
        $customerAddressId = $customer->getDefaultBilling();
        return $this->addressFactory->create()->load($customerAddressId);
    }

    /**
     * Return all of the match keys that can be extracted from order information
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getUserDataFromOrder(): array
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order) {
            return [];
        }

        $userData = [];

        $userData[AAMSettingsFields::EXTERNAL_ID] = $order->getCustomerId();
        $userData[AAMSettingsFields::EMAIL] = $this->hashValue($order->getCustomerEmail());
        $userData[AAMSettingsFields::FIRST_NAME] = $this->hashValue($order->getCustomerFirstname());
        $userData[AAMSettingsFields::LAST_NAME] = $this->hashValue($order->getCustomerLastname());
        $userData[AAMSettingsFields::DATE_OF_BIRTH] = $this->hashValue($order->getCustomerDob() ?? '');
        if ($order->getCustomerGender()) {
            $genderId = $order->getCustomerGender();
            $userData[AAMSettingsFields::GENDER] =
                $this->hashValue(
                    $this->customerMetadata->getAttributeMetadata('gender')
                        ->getOptions()[$genderId]->getLabel()
                );
        }

        $billingAddress = $order->getBillingAddress();
        if ($billingAddress) {
            $userData[AAMSettingsFields::ZIP_CODE] = $this->hashValue($billingAddress->getPostcode());
            $userData[AAMSettingsFields::CITY] = $this->hashValue($billingAddress->getCity());
            $userData[AAMSettingsFields::PHONE] = $this->hashValue($billingAddress->getTelephone());
            $userData[AAMSettingsFields::STATE] = $this->hashValue($billingAddress->getRegionCode());
            $userData[AAMSettingsFields::COUNTRY] = $this->hashValue($billingAddress->getCountryId());
        }

        return array_filter($userData);
    }

    /**
     * Return all of the match keys that can be extracted from user session
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getUserDataFromSession(): array
    {
        $customer = $this->getCurrentCustomer();
        if (!$customer) {
            return [];
        }

        $userData = [];

        $userData[AAMSettingsFields::EXTERNAL_ID] = $customer->getId();
        $userData[AAMSettingsFields::EMAIL] = $this->hashValue($customer->getEmail());
        $userData[AAMSettingsFields::FIRST_NAME] = $this->hashValue($customer->getFirstname());
        $userData[AAMSettingsFields::LAST_NAME] = $this->hashValue($customer->getLastname());
        $userData[AAMSettingsFields::DATE_OF_BIRTH] = $this->hashValue($customer->getDob());
        if ($customer->getGender()) {
            $genderId = $customer->getGender();
            $userData[AAMSettingsFields::GENDER] =
                $this->hashValue(
                    $this->customerMetadata->getAttributeMetadata('gender')
                        ->getOptions()[$genderId]->getLabel()
                );
        }

        $billingAddress = $this->getCustomerAddress($customer);
        if ($billingAddress) {
            $userData[AAMSettingsFields::ZIP_CODE] = $this->hashValue($billingAddress->getPostcode());
            $userData[AAMSettingsFields::CITY] = $this->hashValue($billingAddress->getCity());
            $userData[AAMSettingsFields::PHONE] = $this->hashValue($billingAddress->getTelephone());
            $userData[AAMSettingsFields::STATE] = $this->hashValue($billingAddress->getRegionCode());
            $userData[AAMSettingsFields::COUNTRY] = $this->hashValue($billingAddress->getCountryId());
        }

        return array_filter($userData);
    }

    /**
     * Get Hash value
     *
     * @param string $string
     * @return string
     */
    private function hashValue($string): string
    {
        return hash('sha256', strtolower($string));
    }

    /**
     * Get active quote
     *
     * @return Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuote(): Quote
    {
        if (null === $this->quote) {
            $this->quote = $this->checkoutSession->getQuote();
        }
        return $this->quote;
    }

    // TODO Remaining user/custom data methods that can be obtained using Magento.
}
