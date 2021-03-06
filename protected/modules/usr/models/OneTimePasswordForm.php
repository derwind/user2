<?php

/**
 * OneTimePasswordForm class.
 * OneTimePasswordForm is the data structure for keeping
 * one time password secret form data. It is used by the 'toggleOneTimePassword' action of 'DefaultController'.
 */
class OneTimePasswordForm extends CFormModel
{
	public $oneTimePassword;

	private $_identity;

	private $_mode;
	private $_authenticator;
	private $_secret;

	private $_previousCounter;
	private $_previousCode;

	/**
	 * Declares the validation rules.
	 */
	public function rules()
	{
		return array(
			array('oneTimePassword', 'filter', 'filter'=>'trim'),
			array('oneTimePassword', 'default', 'setOnEmpty'=>true, 'value' => null),
			array('oneTimePassword', 'required'),
			array('oneTimePassword', 'validOneTimePassword'),
		);
	}

	/**
	 * Declares attribute labels.
	 */
	public function attributeLabels()
	{
		return array(
			'oneTimePassword' => Yii::t('UsrModule.usr','One Time Password'),
		);
	}

	public function setMode($mode)
	{
		$this->_mode = $mode;
		return $this;
	}

	public function setAuthenticator($authenticator)
	{
		$this->_authenticator = $authenticator;
		return $this;
	}

	public function setSecret($secret)
	{
		$this->_secret = $secret;
		return $this;
	}

	public function getPreviousCode()
	{
		if ($this->_previousCode === null) {
			list($this->_previousCode, $this->_previousCounter) = $this->getIdentity()->getOneTimePassword();
		}
		return $this->_previousCode;
	}

	public function getPreviousCounter()
	{
		if ($this->_previousCounter === null) {
			list($this->_previousCode, $this->_previousCounter) = $this->getIdentity()->getOneTimePassword();
		}
		return $this->_previousCounter;
	}

	public function getNewCode()
	{
		return $this->_authenticator->getCode($this->_secret, $this->_mode == UsrModule::OTP_TIME ? null : $this->getPreviousCounter());
	}

    public function getUrl($user, $hostname, $secret) {
        $url =  "otpauth://totp/$user@$hostname%3Fsecret%3D$secret";
        $encoder = "https://www.google.com/chart?chs=200x200&chld=M|0&cht=qr&chl=";
        return $encoder.$url;
	}

	public function getIdentity()
	{
		if($this->_identity===null) {
			$userIdentityClass = Yii::app()->controller->module->userIdentityClass;
			$this->_identity = $userIdentityClass::find(array('id'=>Yii::app()->user->getId()));
			if (!($this->_identity instanceof IOneTimePasswordIdentity)) {
				throw new CException(Yii::t('UsrModule.usr','The {class} class must implement the {interface} interface.',array('{class}'=>get_class($this->_identity),'{interface}'=>'IOneTimePasswordIdentity')));
			}
		}
		return $this->_identity;
	}

	public function validOneTimePassword($attribute,$params)
	{
		if ($this->_mode === UsrModule::OTP_TIME) {
			$valid = $this->_authenticator->checkCode($this->_secret, $this->$attribute);
		} elseif ($this->_mode === UsrModule::OTP_COUNTER) {
			$valid = $this->_authenticator->getCode($this->_secret, $this->getPreviousCounter()) == $this->$attribute;
		} else {
			$valid = false;
		}
		if (!$valid) {
			$this->addError($attribute,Yii::t('UsrModule.usr','Entered code is invalid.'));
			return false;
		}
		if ($this->$attribute == $this->getPreviousCode()) {
			if ($this->_mode === UsrModule::OTP_TIME) {
				$message = Yii::t('UsrModule.usr','Please wait until next code will be generated.');
			} elseif ($this->_mode === UsrModule::OTP_COUNTER) {
				$message = Yii::t('UsrModule.usr','Please log in again to request a new code.');
			}
			$this->addError($attribute,Yii::t('UsrModule.usr','Entered code has already been used.').' '.$message);
			$this->scenario = 'verifyOTP';
			return false;
		}
		return true;
	}
}
