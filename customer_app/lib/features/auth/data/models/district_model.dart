class DistrictModel {
  final int id;
  final String name;

  const DistrictModel({required this.id, required this.name});

  factory DistrictModel.fromJson(Map<String, dynamic> json) => DistrictModel(
    id: json['id'] as int,
    name: json['name'] as String,
  );
}
