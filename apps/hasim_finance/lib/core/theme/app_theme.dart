import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:hasim_finance/core/config/app_config.dart';

class AppTheme {
  static const Color brand = Color(AppConfig.brandPrimary);

  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(
      seedColor: brand,
      primary: brand,
      brightness: Brightness.light,
      surface: const Color(0xFFF5FAF8),
    );
    return _base(scheme, GoogleFonts.cairoTextTheme(), Colors.white, const Color(0xFFF5FAF8));
  }

  static ThemeData dark() {
    final scheme = ColorScheme.fromSeed(
      seedColor: brand,
      primary: brand,
      brightness: Brightness.dark,
      surface: const Color(0xFF0B1412),
    );
    return _base(
      scheme,
      GoogleFonts.cairoTextTheme(ThemeData.dark().textTheme),
      const Color(0xFF15201D),
      const Color(0xFF0B1412),
    );
  }

  static ThemeData _base(
    ColorScheme scheme,
    TextTheme textTheme,
    Color card,
    Color scaffold,
  ) {
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      textTheme: textTheme,
      scaffoldBackgroundColor: scaffold,
      appBarTheme: AppBarTheme(
        centerTitle: false,
        backgroundColor: card,
        foregroundColor: scheme.onSurface,
        elevation: 0,
        titleTextStyle: textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: card,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        isDense: true,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(40, 40),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(40, 40),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        ),
      ),
      navigationRailTheme: NavigationRailThemeData(
        indicatorColor: brand.withValues(alpha: 0.16),
      ),
    );
  }
}
