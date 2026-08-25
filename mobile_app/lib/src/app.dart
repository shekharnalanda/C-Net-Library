import 'package:flutter/material.dart';
import 'core/api_client.dart';
import 'core/token_store.dart';
import 'screens/login_screen.dart';
import 'screens/main_shell.dart';

class CNetLibraryApp extends StatefulWidget {
  const CNetLibraryApp({super.key});

  @override
  State<CNetLibraryApp> createState() => _CNetLibraryAppState();
}

class _CNetLibraryAppState extends State<CNetLibraryApp> {
  final TokenStore _tokenStore = TokenStore();
  late final ApiClient _api = ApiClient(_tokenStore);
  bool? _signedIn;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    final token = await _tokenStore.read();
    if (!mounted) return;
    setState(() => _signedIn = token != null && token.isNotEmpty);
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'C-Net Library',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(useMaterial3: true, colorSchemeSeed: Colors.indigo),
      home: _signedIn == null
          ? const _SplashScreen()
          : _signedIn!
              ? MainShell(api: _api, tokenStore: _tokenStore, onSignedOut: () => setState(() => _signedIn = false))
              : LoginScreen(api: _api, tokenStore: _tokenStore, onSignedIn: () => setState(() => _signedIn = true)),
    );
  }
}

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.local_library_rounded, size: 72),
            SizedBox(height: 16),
            Text('C-Net Library', style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold)),
            SizedBox(height: 8),
            Text('Powered by MCI Educational Group'),
            SizedBox(height: 24),
            CircularProgressIndicator(),
          ],
        ),
      ),
    );
  }
}
