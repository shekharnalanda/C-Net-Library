import 'dart:convert';
import 'package:http/http.dart' as http;
import 'token_store.dart';

class ApiClient {
  ApiClient(this.tokenStore, {this.onUnauthorized});

  static const String baseUrl = 'https://cnetlibrary.mciedu.com/api/mobile/v1';
  final TokenStore tokenStore;
  final Future<void> Function()? onUnauthorized;

  Future<Map<String, dynamic>> post(String path, {Map<String, dynamic>? body, bool authenticated = false}) async {
    final response = await http.post(
      Uri.parse('$baseUrl$path'),
      headers: await _headers(authenticated),
      body: jsonEncode(body ?? <String, dynamic>{}),
    );
    return _decode(response, authenticated: authenticated);
  }

  Future<Map<String, dynamic>> get(String path) async {
    final response = await http.get(Uri.parse('$baseUrl$path'), headers: await _headers(true));
    return _decode(response, authenticated: true);
  }

  Future<Map<String, String>> _headers(bool authenticated) async {
    final headers = <String, String>{'Accept': 'application/json', 'Content-Type': 'application/json'};
    if (authenticated) {
      final token = await tokenStore.read();
      if (token != null && token.isNotEmpty) headers['Authorization'] = 'Bearer $token';
    }
    return headers;
  }

  Future<Map<String, dynamic>> _decode(http.Response response, {required bool authenticated}) async {
    Map<String, dynamic> data = <String, dynamic>{};
    if (response.body.isNotEmpty) {
      final decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) data = decoded;
    }

    if (response.statusCode == 401 && authenticated) {
      await tokenStore.clear();
      await onUnauthorized?.call();
      throw ApiException(401, data['message']?.toString() ?? 'Session expired. Please sign in again.');
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(response.statusCode, data['message']?.toString() ?? 'Request failed');
    }
    return data;
  }
}

class ApiException implements Exception {
  ApiException(this.statusCode, this.message);
  final int statusCode;
  final String message;
  @override
  String toString() => message;
}
