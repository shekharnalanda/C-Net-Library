import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class TokenStore {
  static const _key = 'cnet_library_api_token';
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  Future<void> write(String token) => _storage.write(key: _key, value: token);
  Future<String?> read() => _storage.read(key: _key);
  Future<void> clear() => _storage.delete(key: _key);
}
