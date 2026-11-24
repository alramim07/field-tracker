import 'user_profile.dart';

abstract class ProfileRepository {
  Future<UserProfile> fetchProfile();
  Future<UserProfile> updateProfile({String? phone, String? email});
  Future<String> uploadPhoto({required String filePath, required String filename});
  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  });
}
