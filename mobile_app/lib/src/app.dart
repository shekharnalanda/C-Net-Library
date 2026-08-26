import 'package:flutter/material.dart';
import 'core/api_client.dart';
import 'core/token_store.dart';
import 'screens/admin_shell.dart';
import 'screens/home_screen.dart';
import 'screens/main_shell.dart';

class CNetLibraryApp extends StatefulWidget {
  const CNetLibraryApp({super.key});

  @override
  State<CNetLibraryApp> createState() => _CNetLibraryAppState();
}

class _CNetLibraryAppState extends State<CNetLibraryApp> {
  final TokenStore _tokenStore = TokenStore();
  late final ApiClient _api = ApiClient(
    _tokenStore,
    onUnauthorized: () async {
      if (!mounted) return;
      await _tokenStore.clear();
      setState(() {
        _signedIn = false;
        _role = null;
      });
    },
  );

  bool? _signedIn;
  String? _role;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    final token = await _tokenStore.read();
    final role = await _tokenStore.readRole();
    if (!mounted) return;

    final hasValidLocalSession = token != null && token.isNotEmpty && (role == 'student' || role == 'admin');
    if (!hasValidLocalSession && token != null) await _tokenStore.clear();

    setState(() {
      _signedIn = hasValidLocalSession;
      _role = hasValidLocalSession ? role : null;
    });
  }

  Future<void> _refreshSignedInState() async {
    final role = await _tokenStore.readRole();
    if (!mounted) return;
    setState(() {
      _signedIn = true;
      _role = role;
    });
    if (Navigator.of(context).canPop()) Navigator.of(context).popUntil((route) => route.isFirst);
  }

  void _signedOut() {
    setState(() {
      _signedIn = false;
      _role = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'C-Net Library',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorSchemeSeed: const Color(0xFF263A7A),
        inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder()),
        cardTheme: const CardThemeData(elevation: 0.6, margin: EdgeInsets.zero),
      ),
      home: _signedIn == null
          ? const _SplashScreen()
          : !_signedIn!
              ? HomeScreen(api: _api, tokenStore: _tokenStore, onSignedIn: _refreshSignedInState)
              : _role == 'admin'
                  ? AdminShell(api: _api, tokenStore: _tokenStore, onSignedOut: _signedOut)
                  : MainShell(api: _api, tokenStore: _tokenStore, onSignedOut: _signedOut),
    );
  }
}

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                CircleAvatar(radius: 48, child: Icon(Icons.local_library_rounded, size: 54)),
                SizedBox(height: 20),
                Text('C-Net Library', style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold)),
                SizedBox(height: 6),
                Text('Study • Library • Digital Learning', textAlign: TextAlign.center),
                SizedBox(height: 8),
                Text('Powered by MCI Educational Group'),
                SizedBox(height: 28),
                CircularProgressIndicator(),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
