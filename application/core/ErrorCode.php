<?php
class ErrorCode{
	public static $OK = 0;
	
	public static $ParamError = -1;
	public static $IllegalJsonString = -2;
	public static $IllegalRequest = -3;
	public static $IllegalUser = -4;
	public static $UnBind = -5;
	public static $PermissionDenied = -6;
	public static $DbError = -10;
	public static $DbEmpty = -11;
	
	public static $UploadError = -20;
	
	public static $IllegalAesKey = -41001;
	public static $IllegalIv = -41002;
	public static $IllegalBuffer = -41003;
	public static $DecodeBase64Error = -41004;
}
