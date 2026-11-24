import 'package:material_ui/material_ui.dart';

import 'user_profile.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({
    super.key,
    required this.profile,
    required this.onSaveProfile,
    required this.onUploadPhoto,
  });

  final UserProfile profile;
  final Future<UserProfile> Function({
    required String email,
    required String? phone,
  }) onSaveProfile;
  final Future<String?> Function() onUploadPhoto;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late final TextEditingController _emailController;
  late final TextEditingController _phoneController;
  late UserProfile _profile;
  bool _saving = false;
  bool _uploadingPhoto = false;
  String? _message;

  @override
  void initState() {
    super.initState();
    _profile = widget.profile;
    _emailController = TextEditingController(text: _profile.email);
    _phoneController = TextEditingController(text: _profile.phone ?? '');
  }

  @override
  void dispose() {
    _emailController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_saving) return;
    setState(() {
      _saving = true;
      _message = null;
    });
    try {
      final updated = await widget.onSaveProfile(
        email: _emailController.text.trim(),
        phone: _phoneController.text.trim().isEmpty
            ? null
            : _phoneController.text.trim(),
      );
      if (!mounted) return;
      setState(() {
        _profile = updated;
        _emailController.text = updated.email;
        _phoneController.text = updated.phone ?? '';
        _message = 'Profile updated';
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _message = 'Failed to update profile');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _uploadPhoto() async {
    if (_uploadingPhoto) return;
    setState(() {
      _uploadingPhoto = true;
      _message = null;
    });
    try {
      final url = await widget.onUploadPhoto();
      if (!mounted) return;
      if (url != null && url.isNotEmpty) {
        setState(() {
          _profile = _profile.copyWith(photoUrl: url);
          _message = 'Photo updated';
        });
      }
    } catch (_) {
      if (!mounted) return;
      setState(() => _message = 'Failed to update photo');
    } finally {
      if (mounted) setState(() => _uploadingPhoto = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Update profile')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Center(
            child: Column(
              children: [
                CircleAvatar(
                  radius: 44,
                  backgroundImage:
                      (_profile.photoUrl != null && _profile.photoUrl!.isNotEmpty)
                          ? NetworkImage(_profile.photoUrl!)
                          : null,
                  child: (_profile.photoUrl == null || _profile.photoUrl!.isEmpty)
                      ? const Icon(Icons.person, size: 44)
                      : null,
                ),
                const SizedBox(height: 8),
                TextButton.icon(
                  key: const Key('upload_photo_button'),
                  onPressed: _uploadingPhoto ? null : _uploadPhoto,
                  icon: _uploadingPhoto
                      ? const SizedBox(
                          width: 16,
                          height: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.photo_camera_outlined),
                  label: const Text('Update photo'),
                ),
              ],
            ),
          ),
          if (_message != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(
                _message!,
                key: const Key('profile_message'),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ),
          const SizedBox(height: 8),
          Text('Editable information', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          TextFormField(
            key: const Key('profile_email'),
            controller: _emailController,
            decoration: const InputDecoration(labelText: 'Email'),
            keyboardType: TextInputType.emailAddress,
          ),
          const SizedBox(height: 12),
          TextFormField(
            key: const Key('profile_phone'),
            controller: _phoneController,
            decoration: const InputDecoration(labelText: 'Phone number'),
            keyboardType: TextInputType.phone,
          ),
          const SizedBox(height: 16),
          FilledButton(
            key: const Key('save_profile_button'),
            onPressed: _saving ? null : _save,
            child: _saving
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('Save changes'),
          ),
          const SizedBox(height: 24),
          Text('Admin-managed information', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          _LockedField(label: 'Name', value: _profile.name),
          _LockedField(label: 'Staff ID', value: _profile.staffId ?? '-'),
          _LockedField(label: 'Duty time', value: _profile.dutyTimeLabel),
          _LockedField(label: 'Tracking time', value: _profile.trackingTimeLabel),
          _LockedField(label: 'Designation', value: _profile.designation ?? '-'),
        ],
      ),
    );
  }
}

class _LockedField extends StatelessWidget {
  const _LockedField({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          suffixIcon: const Icon(Icons.lock_outline, size: 18),
          enabledBorder: OutlineInputBorder(
            borderSide: BorderSide(color: Theme.of(context).colorScheme.outlineVariant),
          ),
        ),
        child: Text(
          value.isEmpty ? '-' : value,
          style: Theme.of(context).textTheme.bodyLarge,
        ),
      ),
    );
  }
}
