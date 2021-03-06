<?php

namespace Omnipay\GiroCheckout\Message;

use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Tests\TestCase;

class AuthorizeRequestCreditCardTest extends TestCase
{
    /**
     * @var Gateway
     */
    protected $request;

    public function setUp()
    {
        parent::setUp();

        $this->request = new AuthorizeRequest($this->getHttpClient(), $this->getHttpRequest());

        $this->request->initialize([
            'paymentType' => 'CreditCard',
            'merchantId' => 12345678,
            'projectId' => 654321,
            'transactionId' => 'trans-id-123',
            'amount' => '1.23',
            'currency' => 'EUR',
            'description' => 'A lovely test authorisation',
            'language' => 'en',
            'mobile' => true,
        ]);
    }

    /**
     * @expectedException \Omnipay\Common\Exception\InvalidRequestException
     * @expectedExceptionMessage Missing cardReference for a payment without a payment page.
     */
    public function testPaymentPageNoCardReference()
    {
        // With no payment page, there will be no form modifiers and no return URL.
        $this->request->setPaymentPage(false);
        $this->request->getData();
    }

    public function testPaymentPage()
    {
        // With a payment page, there will be form modifiers and no return URL.

        $data = $this->request->getData();

        $this->assertArrayHasKey('urlRedirect', $data);
        $this->assertArrayHasKey('mobile', $data);
        $this->assertArrayHasKey('locale', $data);

        // With no payment page, there will be no form modifiers and no return URL.

        $this->request->setPaymentPage(false);
        $this->request->setCardReference('abcdefgh1234567890');

        $data = $this->request->getData();

        $this->assertArrayNotHasKey('urlRedirect', $data);
        $this->assertArrayNotHasKey('mobile', $data);
        $this->assertArrayNotHasKey('locale', $data);
    }

    /**
     * @expectedException Omnipay\Common\Exception\InvalidRequestException
     */
    public function testMerchantIdString()
    {
        $this->request->setMerchantId('ABCDEFG');
        $this->request->getMerchantId(true);
    }

    /**
     * @expectedException Omnipay\Common\Exception\InvalidRequestException
     */
    public function testProjectIdString()
    {
        $this->request->setProjectId('ABCDEFG');
        $this->request->getProjectId(true);
    }

    public function testPurposeTruncate()
    {
        // 100 character description in.
        $this->request->setDescription(str_repeat('X', 100));

        $data = $this->request->getData();

        // 27 character description out.
        $this->assertSame(str_repeat('X', 27), $data['purpose']);
    }

    public function testLanguages()
    {
        foreach(['en', 'EN', 'en-GB', 'en_GB'] as $locale) {
            $this->request->setLanguage($locale);

            $data = $this->request->getData();

            $this->assertSame(
                'en',
                isset($data['locale']) ? $data['locale'] : 'NOT SET',
                sprintf('Locale "%s" does not translate to language "en"', $locale)
            );
        }
    }

    public function testCreateCard()
    {
        $this->request->setCreateCard(true);
        $data = $this->request->getData();

        $this->assertSame('create', $data['pkn']);

        $this->request->setCreateCard(false);
        $data = $this->request->getData();

        $this->assertArrayNotHasKey('pkn', $data);

        $this->request->setCardReference('1234567812345678');
        $data = $this->request->getData();

        $this->assertSame('1234567812345678', $data['pkn']);

        // If the card reference is set, then asking for a new card reference
        // to be created, will have no effect.

        $this->request->setCreateCard(true);
        $data = $this->request->getData();

        $this->assertSame('1234567812345678', $data['pkn']);
    }

    // Recurring payments are now handled by their own message.
    /*public function testRecurring()
    {
        $this->request->setRecurring(null);
        $data = $this->request->getData();
        $this->assertArrayNotHasKey('recurring', $data);

        $this->request->setRecurring(true);
        $data = $this->request->getData();
        $this->assertSame('1', $data['recurring']);

        $this->request->setRecurring('YES');
        $data = $this->request->getData();
        $this->assertSame('1', $data['recurring']);

        $this->request->setRecurring(false);
        $data = $this->request->getData();
        $this->assertSame('0', $data['recurring']);
    }*/

    public function testHash()
    {
        $data = $this->request->setRecurring(false);

        // This hash will change if the initializartion data changes.
        $data = $this->request->getData();
        $this->assertSame('ef3f143c59630934ffb36b14105140f7', $data['hash']);

        $data = [
            'merchantId' => '1234567',
            'projectId' => '1234',
            'parameter1' => 'Wert1',
            'parameter2' => 'Wert2',
        ];

        $this->request->setProjectPassphrase('secret');

        // Note: the example in the docs here:
        // http://api.girocheckout.de/en:girocheckout:general:start#hash_generation
        // give the following hash: '4233d4d15a75d651d60ebabe99b3d846'
        // However, it is not clear if that has is correct, as the following line shows:
        // var_dump(hash_hmac('MD5', '12345671234Wert1Wert2', 'secret'));
        // Gives '184d3f805959fc9fff2d07ccec1d1022'

        $this->assertSame('184d3f805959fc9fff2d07ccec1d1022', $this->request->requestHash($data));
    }
}
