import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class TokenStore {
  static const _tokenKey = 'cnet_library_api_token';
  static const _roleKey = 'cnet_library_session_role';
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  Future<void> write(String token, {required String role}) async {
    await _storage.write(key: _tokenKey, value: token);
    await _storage.write(key: _roleKey, value: role);
  }

  Future<String?> read() => _storage.read(key: _tokenKey);
  Future<String?> readRole() => _storage.read(key: _roleKey);

  Future<void> clear() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _roleKey);
  }
}
