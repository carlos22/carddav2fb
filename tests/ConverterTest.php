<?php

use \Andig\FritzBox\Converter;
use \PHPUnit\Framework\TestCase;

class ConverterTest extends TestCase
{
    /** @var Converter */
    public $converter;

    /** @var SimpleXMLElement */
    public $contact;

    public function setUp()
    {
        $this->converter = new Converter($this->defaultConfig());
        $this->contact = $this->defaultContact();
    }

    private function defaultConfig(): array
    {
        return [
            'conversions' => [
                'phoneTypes' => [
                    'WORK' => 'work',
                    'HOME' => 'home',
                    'CELL' => 'mobile',
                    'FAX' => 'fax_work'
                ],
                'realName' => [],
            ],
        ];
    }

    private function defaultContact(): stdClass
    {
        $c = new stdClass;
        $c->uid = 'uid';
        $c->phone = new stdClass;
        $c->phone->other = ['1'];

        return $c;
    }

    public function testDefaultContact()
    {
        $res = $this->converter->convert($this->contact);
        $this->assertInternalType('array', $res);
        $this->assertCount(1, $res);

        $contact = $res[0];
        $this->assertInstanceOf(SimpleXMLElement::class, $contact->person);
        $this->assertInstanceOf(SimpleXMLElement::class, $contact->telephony);
        $this->assertInstanceOf(SimpleXMLElement::class, $contact->telephony->number);
    }

    public function testSkipContactWithoutPhone()
    {
        $this->contact->phone = [];

        $res = $this->converter->convert($this->contact);
        $this->assertCount(0, $res);
    }

    public function testEmptyPropertyReplacement()
    {
        $res = $this->converter->convert($this->contact);
        $this->assertCount(1, $res);

        $contact = $res[0];
        $this->assertEquals('', (string)$contact->person->realName);
    }

    public function contactPropertiesProvider(): array
    {
        return [
            [
                [
                    'firstname' => 'foo',
                    'lastname' => 'bar',
                    'organization' => 'orga',
                    'fullname' => 'full',
                ],
                'bar, foo'
            ],
            [
                [
                    'organization' => 'orga',
                    'fullname' => 'full',
                ],
                'orga'
            ],
            [
                [
                    'fullname' => 'full',
                ],
                'full'
            ],
        ];
    }

    /**
     * @dataProvider contactPropertiesProvider
     */
    public function testPropertyReplacement(array $properties, string $realName)
    {
        foreach ($properties as $key => $value) {
            $this->contact->$key = $value;
        }

        // replacement config
        $config = $this->defaultConfig();
        $config['conversions']['realName'] = [
            '{lastname}, {firstname}',
            '{organization}',
            '{fullname}'
        ];

        $res = (new Converter($config))->convert($this->contact);
        $this->assertCount(1, $res);

        $contact = $res[0];
        $this->assertEquals($realName, (string)$contact->person->realName);
    }

    public function testPhoneTypeAreMappedAndOrdered()
    {
        $this->contact->phone = new stdClass;

        $idx = 0;
        $conversions = $this->defaultConfig()['conversions'];
        foreach ($conversions['phoneTypes'] as $key => $value) {
            $phoneType = sprintf('foo;%s;bar', strtolower($key));
            $this->contact->phone->$key = [(string)$idx++];
        }

        $res = $this->converter->convert($this->contact);
        $this->assertCount(1, $res);

        $contact = $res[0];
        $this->assertCount(count($conversions['phoneTypes']), $contact->telephony->children());

        $idx = 0;
        foreach ($conversions['phoneTypes'] as $key => $value) {
            $number = $contact->telephony->children()[$idx];
            $this->assertEquals($value, (string)$number['type']);
            $this->assertEquals((string)$idx++, (string)$number);
        }
    }

    public function testFaxIsMapped()
    {
        $this->contact->phone = new stdClass;
        $this->contact->phone->fax = ['2'];

        $res = $this->converter->convert($this->contact);
        $this->assertCount(1, $res);

        $contact = $res[0];
        $this->assertCount(1, $contact->telephony->children());

        $faxNumber = $contact->telephony->children()[0]; // 1st number
        $this->assertEquals('fax_work', (string)$faxNumber['type']);
    }

    public function testVanityAndQuickdialNumbers()
    {
        $this->contact->phone = new stdClass;
        $this->contact->phone->other = ['1'];
        $this->contact->phone->pref = ['2'];
        $this->contact->xquickdial = 'quickdial';
        $this->contact->xvanity = 'vanity';

        $res = $this->converter->convert($this->contact);
        $this->assertCount(1, $res);

        $contact = $res[0];
        $this->assertCount(2, $contact->telephony->children());

        // first number without pref/vanity
        $vanityNumber = $contact->telephony->children()[0];
        $this->assertNull($vanityNumber['quickdial']);
        $this->assertNull($vanityNumber['vanity']);

        // second number with pref/vanity
        $vanityNumber = $contact->telephony->children()[1];
        $this->assertEquals('quickdial', (string)$vanityNumber['quickdial']);
        $this->assertEquals('vanity', (string)$vanityNumber['vanity']);
    }

    public function testMoreThan10PhoneNumbers()
    {
        $type = 'other';
        $this->contact->phone->$type = [];

        for ($i=1; $i<=18; $i++) {
            $this->contact->phone->$type[] = (string)$i;
        }

        $res = $this->converter->convert($this->contact);
        $this->assertCount(2, $res);

        foreach ($res as $idx => $contact) {
            for ($i=1; $i<=9; $i++) {
                $expect = 9*$idx + $i;
                $this->assertContains((string)$expect, $contact->telephony->number);
            }
        }
    }
}
