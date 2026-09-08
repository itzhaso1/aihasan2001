import 'package:flutter/material.dart';

import '../theme/hasim_colors.dart';
import '../theme/hasim_radius.dart';

/// Compact floating notice at the top of the current Scaffold.
void showHasimTopNotice(BuildContext context, String text) {
  final messenger = ScaffoldMessenger.of(context);
  messenger.hideCurrentSnackBar();
  final media = MediaQuery.of(context);
  const barHeight = 56.0;
  final top = media.padding.top + 8;
  messenger.showSnackBar(
    SnackBar(
      behavior: SnackBarBehavior.floating,
      duration: const Duration(seconds: 4),
      elevation: 1,
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      margin: EdgeInsets.fromLTRB(
        72,
        top,
        72,
        media.size.height - top - barHeight,
      ),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(HasimRadius.sm),
        side: const BorderSide(color: Color(0xFFA7F3D0)),
      ),
      backgroundColor: HasimColors.surface,
      content: Text(
        text,
        textAlign: TextAlign.center,
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          fontSize: 11,
          height: 1.25,
          fontWeight: FontWeight.w700,
          color: HasimColors.ctaDark,
        ),
      ),
    ),
  );
}
