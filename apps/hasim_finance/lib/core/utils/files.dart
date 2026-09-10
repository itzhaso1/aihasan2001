import 'package:flutter/foundation.dart';
import 'package:hasim_finance/core/utils/download_io.dart'
    if (dart.library.html) 'package:hasim_finance/core/utils/download_web.dart' as download;

Future<void> saveAndOpenBytes(Uint8List bytes, String filename) {
  return download.saveAndOpenBytes(bytes, filename);
}

Future<void> copyText(String value) => download.copyText(value);

Future<void> openExternalUrl(String url) => download.openExternalUrl(url);
