import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:hasim_finance/core/theme/finance_tokens.dart';

class AppTheme {
  static const Color brand = FinanceTokens.brand;

  static ThemeData light() {
    final textTheme = GoogleFonts.cairoTextTheme();
    const scheme = ColorScheme.light(
      primary: FinanceTokens.brand,
      onPrimary: Colors.white,
      secondary: FinanceTokens.brandDark,
      onSecondary: Colors.white,
      surface: FinanceTokens.surface,
      onSurface: FinanceTokens.text,
      error: FinanceTokens.danger,
      onError: Colors.white,
      outline: FinanceTokens.borderStrong,
    );
    return _base(scheme, textTheme, Brightness.light);
  }

  static ThemeData dark() {
    final textTheme = GoogleFonts.cairoTextTheme(ThemeData.dark().textTheme);
    final scheme = ColorScheme.fromSeed(
      seedColor: brand,
      primary: brand,
      brightness: Brightness.dark,
      surface: const Color(0xFF121A1F),
    );
    return _base(scheme, textTheme, Brightness.dark);
  }

  static ThemeData _base(ColorScheme scheme, TextTheme textTheme, Brightness brightness) {
    final isLight = brightness == Brightness.light;
    final scaffold = isLight ? FinanceTokens.canvas : const Color(0xFF0B1115);
    final card = isLight ? FinanceTokens.surface : const Color(0xFF152026);
    final border = isLight ? FinanceTokens.border : const Color(0xFF243038);
    final muted = isLight ? FinanceTokens.textMuted : scheme.onSurface.withValues(alpha: 0.65);

    final shaped = textTheme.copyWith(
      headlineLarge: textTheme.headlineLarge?.copyWith(fontWeight: FontWeight.w800, color: scheme.onSurface, letterSpacing: -0.4),
      headlineMedium: textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w800, color: scheme.onSurface),
      headlineSmall: textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800, color: scheme.onSurface),
      titleLarge: textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800, color: scheme.onSurface),
      titleMedium: textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700, color: scheme.onSurface),
      titleSmall: textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700, color: scheme.onSurface),
      bodyLarge: textTheme.bodyLarge?.copyWith(color: scheme.onSurface, height: 1.45),
      bodyMedium: textTheme.bodyMedium?.copyWith(color: scheme.onSurface, height: 1.45),
      bodySmall: textTheme.bodySmall?.copyWith(color: muted, height: 1.4),
      labelLarge: textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w700),
    );

    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      textTheme: shaped,
      scaffoldBackgroundColor: scaffold,
      canvasColor: scaffold,
      dividerColor: border,
      appBarTheme: AppBarTheme(
        centerTitle: false,
        backgroundColor: scaffold,
        foregroundColor: scheme.onSurface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        toolbarHeight: 64,
        titleSpacing: 20,
        titleTextStyle: shaped.headlineSmall?.copyWith(fontWeight: FontWeight.w800, fontSize: 22),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: card,
        margin: EdgeInsets.zero,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(FinanceTokens.radiusLg),
          side: BorderSide(color: border),
        ),
        shadowColor: const Color(0xFF152033).withValues(alpha: 0.06),
      ),
      dividerTheme: DividerThemeData(color: border, space: 1, thickness: 1),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: card,
        isDense: true,
        hintStyle: TextStyle(color: muted, fontSize: 13),
        labelStyle: TextStyle(color: muted, fontWeight: FontWeight.w600, fontSize: 12),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(FinanceTokens.radius),
          borderSide: BorderSide(color: border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(FinanceTokens.radius),
          borderSide: BorderSide(color: border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(FinanceTokens.radius),
          borderSide: const BorderSide(color: FinanceTokens.brand, width: 1.4),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: FinanceTokens.brand,
          foregroundColor: Colors.white,
          minimumSize: const Size(40, FinanceTokens.controlHeight),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(FinanceTokens.radius)),
          textStyle: shaped.labelLarge?.copyWith(fontWeight: FontWeight.w800),
          elevation: 0,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: scheme.onSurface,
          minimumSize: const Size(40, FinanceTokens.controlHeight),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          side: BorderSide(color: border),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(FinanceTokens.radius)),
          textStyle: shaped.labelLarge?.copyWith(fontWeight: FontWeight.w700),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: FinanceTokens.brandDark,
          textStyle: shaped.labelLarge?.copyWith(fontWeight: FontWeight.w700),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: isLight ? FinanceTokens.canvasAlt : card,
        selectedColor: FinanceTokens.brandSoft,
        side: BorderSide(color: border),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(FinanceTokens.radius)),
        labelStyle: shaped.labelLarge?.copyWith(fontSize: 12, fontWeight: FontWeight.w700),
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 0),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: card,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(FinanceTokens.radiusXl)),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(FinanceTokens.radius)),
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: FinanceTokens.brand,
        foregroundColor: Colors.white,
        elevation: 2,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: card,
        indicatorColor: FinanceTokens.brandSoft,
        elevation: 0,
        labelTextStyle: WidgetStatePropertyAll(shaped.labelSmall?.copyWith(fontWeight: FontWeight.w700)),
      ),
      dataTableTheme: DataTableThemeData(
        headingRowColor: WidgetStatePropertyAll(isLight ? FinanceTokens.canvasAlt : card),
        headingTextStyle: shaped.labelLarge?.copyWith(fontSize: 12, color: muted, fontWeight: FontWeight.w800),
        dataTextStyle: shaped.bodyMedium?.copyWith(fontSize: 13),
        dividerThickness: 1,
        headingRowHeight: 40,
        dataRowMinHeight: 44,
        dataRowMaxHeight: 52,
      ),
      listTileTheme: ListTileThemeData(
        iconColor: muted,
        dense: true,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }
}
