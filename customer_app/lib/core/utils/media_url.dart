import '../constants/app_constants.dart';

String fixMediaUrl(String? url) {
  if (url == null || url.isEmpty) return '';
  final canonical  = 'https://${AppConstants.baseDomain}';
  final api        = 'https://${AppConstants.apiDomain}';
  final mediaProxy = '$canonical/api/v1/media?f=';
  return url
      .replaceAll('$api/', '$canonical/')
      .replaceAll('http://${AppConstants.apiDomain}/', '$canonical/')
      .replaceAll('$canonical/storage/', mediaProxy)
      .replaceAll('http://${AppConstants.baseDomain}/storage/', mediaProxy);
}
