<?php
require_once __DIR__ . '/../bootstrap.php';
require_once ROOT_PATH . '/public/api/_errors.php';
require_once ROOT_PATH . '/public/api/_validator.php';

use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    public function testRequiredPass()
    {
        $v = new Validator(['name' => 'Test']);
        $v->required('name');
        $this->assertTrue($v->validate());
        $this->assertEmpty($v->getErrors());
    }

    public function testRequiredFail()
    {
        $v = new Validator([]);
        $v->required('name');
        $this->assertFalse($v->validate());
        $errors = $v->getErrors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertEquals(ERR_REQUIRED_FIELD, $errors['name']['code']);
    }

    public function testIntegerPass()
    {
        $v = new Validator(['age' => 25]);
        $v->integer('age');
        $this->assertTrue($v->validate());
    }

    public function testIntegerFail()
    {
        $v = new Validator(['age' => 'twenty-five']);
        $v->integer('age');
        $this->assertFalse($v->validate());
        $errors = $v->getErrors();
        $this->assertEquals(ERR_INVALID_INPUT, $errors['age']['code']);
    }

    public function testMaxLengthPass()
    {
        $v = new Validator(['name' => 'Short']);
        $v->maxLength('name', 10);
        $this->assertTrue($v->validate());
    }

    public function testMaxLengthFail()
    {
        $v = new Validator(['name' => 'Very Long Name']);
        $v->maxLength('name', 5);
        $this->assertFalse($v->validate());
        $errors = $v->getErrors();
        $this->assertEquals(ERR_VALUE_OUT_OF_RANGE, $errors['name']['code']);
    }

    public function testInArrayPass()
    {
        $v = new Validator(['status' => 'active']);
        $v->inArray('status', ['active', 'inactive']);
        $this->assertTrue($v->validate());
    }

    public function testInArrayFail()
    {
        $v = new Validator(['status' => 'unknown']);
        $v->inArray('status', ['active', 'inactive']);
        $this->assertFalse($v->validate());
        $errors = $v->getErrors();
        $this->assertEquals(ERR_INVALID_INPUT, $errors['status']['code']);
    }

    public function testChainedValidation()
    {
        $v = new Validator(['name' => 'Test', 'age' => 25]);
        $v->required('name')
          ->required('age')
          ->string('name')
          ->integer('age')
          ->maxLength('name', 100);
        $this->assertTrue($v->validate());
        $this->assertEmpty($v->getErrors());
    }

    public function testMinLengthPass()
    {
        $v = new Validator(['name' => 'LongEnough']);
        $v->minLength('name', 5);
        $this->assertTrue($v->validate());
    }

    public function testMinLengthFail()
    {
        $v = new Validator(['name' => 'Hi']);
        $v->minLength('name', 5);
        $this->assertFalse($v->validate());
        $errors = $v->getErrors();
        $this->assertEquals(ERR_VALUE_OUT_OF_RANGE, $errors['name']['code']);
    }

    public function testEmailPass()
    {
        $v = new Validator(['email' => 'test@example.com']);
        $v->email('email');
        $this->assertTrue($v->validate());
    }

    public function testEmailFail()
    {
        $v = new Validator(['email' => 'not-an-email']);
        $v->email('email');
        $this->assertFalse($v->validate());
        $errors = $v->getErrors();
        $this->assertEquals(ERR_INVALID_INPUT, $errors['email']['code']);
    }

    public function testGetFirstError()
    {
        $v = new Validator([]);
        $v->required('name')->required('email');
        $firstError = $v->getFirstError();
        $this->assertNotNull($firstError);
        $this->assertEquals(ERR_REQUIRED_FIELD, $firstError['code']);
    }

    public function testMultipleErrorsOnSameField()
    {
        $v = new Validator(['field' => '']);
        $v->required('field')->minLength('field', 5);
        $errors = $v->getErrors();
        // Only the first validation should set the error (required)
        $this->assertArrayHasKey('field', $errors);
    }

    public function testSkipValidationWhenFieldMissing()
    {
        $v = new Validator([]);
        // Should not add errors for missing fields on optional validators
        $v->integer('age')->email('email')->maxLength('name', 10);
        $this->assertTrue($v->validate());
        $this->assertEmpty($v->getErrors());
    }
}
