<?php
declare(strict_types=1);
/** Provider adapter boundary. Implement the selected Iranian SMS vendor here. */
final class SmsGateway {
 public function send(string $mobile,string $message): bool {
  $provider=getenv('SMS_PROVIDER') ?: '';
  $key=getenv('SMS_API_KEY') ?: '';
  if($provider==='' || $key==='') { error_log('[NEXA SMS DEV] '.$mobile.' :: '.$message); return false; }
  // Intentionally fail closed until a vendor-specific endpoint and payload are configured.
  error_log('SMS provider configured but adapter is not implemented: '.$provider);
  return false;
 }
}
