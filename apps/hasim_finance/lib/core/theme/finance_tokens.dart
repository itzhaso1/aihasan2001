import 'package:flutter/material.dart';
import 'package:hasim_finance/core/config/app_config.dart';

/// Central Finance visual language. Screens must not invent local colors.
class FinanceTokens {
  FinanceTokens._();

  static const Color brand = Color(AppConfig.brandPrimary);
  static const Color brandDark = Color(AppConfig.brandDark);
  static const Color brandSoft = Color(0xFFE7F8F4);
  static const Color brandIconBg = Color(0xFFD8F4EE);

  static const Color canvas = Color(0xFFF3F6F8);
  static const Color canvasAlt = Color(0xFFF7FAFC);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color sidebar = Color(0xFFFFFFFF);
  static const Color header = Color(0xFFFFFFFF);

  static const Color border = Color(0xFFE6EDF2);
  static const Color borderStrong = Color(0xFFD7E0E8);
  static const Color divider = Color(0xFFEEF3F6);

  static const Color text = Color(0xFF152033);
  static const Color textMuted = Color(0xFF6B7A8D);
  static const Color textFaint = Color(0xFF93A0B0);

  static const Color success = Color(0xFF0F9F7A);
  static const Color successSoft = Color(0xFFE6F7F1);
  static const Color warning = Color(0xFFD97706);
  static const Color warningSoft = Color(0xFFFEF3C7);
  static const Color danger = Color(0xFFDC2626);
  static const Color dangerSoft = Color(0xFFFEE2E2);
  static const Color info = Color(0xFF2563EB);
  static const Color infoSoft = Color(0xFFDBEAFE);
  static const Color pink = Color(0xFFDB2777);
  static const Color pinkSoft = Color(0xFFFCE7F3);
  static const Color orange = Color(0xFFEA580C);
  static const Color orangeSoft = Color(0xFFFFEDD5);
  static const Color indigo = Color(0xFF4F46E5);
  static const Color indigoSoft = Color(0xFFE0E7FF);
  static const Color amber = Color(0xFFF59E0B);
  static const Color amberSoft = Color(0xFFFEF3C7);

  static const double radiusSm = 8;
  static const double radius = 12;
  static const double radiusLg = 16;
  static const double radiusXl = 20;
  static const double radiusPill = 999;

  static const double space4 = 4;
  static const double space8 = 8;
  static const double space12 = 12;
  static const double space16 = 16;
  static const double space20 = 20;
  static const double space24 = 24;

  static const double sidebarWidth = 248;
  static const double sidebarWidthWide = 268;
  static const double headerHeight = 64;
  static const double controlHeight = 42;

  static List<BoxShadow> get cardShadow => [
        BoxShadow(
          color: const Color(0xFF152033).withValues(alpha: 0.04),
          blurRadius: 18,
          offset: const Offset(0, 8),
        ),
      ];

  static List<BoxShadow> get softShadow => [
        BoxShadow(
          color: const Color(0xFF152033).withValues(alpha: 0.035),
          blurRadius: 12,
          offset: const Offset(0, 4),
        ),
      ];

  static BoxDecoration card({Color? color, double radius = radiusLg}) {
    return BoxDecoration(
      color: color ?? surface,
      borderRadius: BorderRadius.circular(radius),
      border: Border.all(color: border),
      boxShadow: cardShadow,
    );
  }

  static BoxDecoration sidebarItem({required bool selected}) {
    return BoxDecoration(
      color: selected ? brandSoft : Colors.transparent,
      borderRadius: BorderRadius.circular(10),
    );
  }
}

class FinanceIconTone {
  const FinanceIconTone(this.foreground, this.background);
  final Color foreground;
  final Color background;

  static const teal = FinanceIconTone(FinanceTokens.brandDark, FinanceTokens.brandIconBg);
  static const rose = FinanceIconTone(FinanceTokens.pink, FinanceTokens.pinkSoft);
  static const orange = FinanceIconTone(FinanceTokens.orange, FinanceTokens.orangeSoft);
  static const amber = FinanceIconTone(Color(0xFFB45309), FinanceTokens.amberSoft);
  static const indigo = FinanceIconTone(FinanceTokens.indigo, FinanceTokens.indigoSoft);
  static const blue = FinanceIconTone(FinanceTokens.info, FinanceTokens.infoSoft);
  static const red = FinanceIconTone(FinanceTokens.danger, FinanceTokens.dangerSoft);
  static const green = FinanceIconTone(FinanceTokens.success, FinanceTokens.successSoft);
}
