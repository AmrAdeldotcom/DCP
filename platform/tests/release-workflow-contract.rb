#!/usr/bin/env ruby
require 'yaml'

ROOT = File.expand_path('../..', __dir__)
def load_workflow(name)
  YAML.load_file(File.join(ROOT, '.github', 'workflows', name))
end
def assert(condition, message)
  raise "FAIL: #{message}" unless condition
  puts "PASS: #{message}"
end
def workflow_triggers(workflow)
  workflow['on'] || workflow[true] || {}
end

release = load_workflow('release.yml')
trigger = workflow_triggers(release)
assert(Array(trigger.dig('push', 'tags')).include?('v*'), 'single release orchestrator runs for version tags')
jobs = release.fetch('jobs')
assert(jobs.dig('linux', 'uses') == './.github/workflows/release-linux.yml', 'release orchestrator calls Linux package builder')
assert(jobs.dig('windows', 'uses') == './.github/workflows/release-windows.yml', 'release orchestrator calls Windows package builder')
assert(jobs.dig('macos', 'uses') == './.github/workflows/release-macos.yml', 'release orchestrator calls native Intel and Apple Silicon macOS builder')
publish = jobs.fetch('publish-signed-release')
assert(%w[linux windows macos].all? { |name| Array(publish['needs']).include?(name) }, 'signed release waits for every platform package')
steps = publish.fetch('steps')
merge_guard = steps.find { |step| step['name'].to_s.include?('Require release tag commit') }
assert(merge_guard && merge_guard['run'].include?('git merge-base --is-ancestor'), 'release signing is blocked until the tag commit is merged into the default branch')
assembly_test = steps.find { |step| step['name'].to_s.include?('ephemeral test key') }
assert(assembly_test && assembly_test['run'].include?('platform/tests/release-assembly.sh'), 'release workflow runs a four-platform assembly test before using the production key')
download = steps.find { |step| step['uses'].to_s.start_with?('actions/download-artifact@') }
assert(download && download.dig('with', 'pattern') == 'dcp-*-package-release', 'release assembly downloads only platform package artifacts')
assembly = steps.find { |step| step['name'].to_s.include?('Build and sign synchronized') }
assert(assembly && assembly.dig('env', 'DCP_RELEASE_SIGNING_PRIVATE_KEY_B64') == '${{ secrets.DCP_RELEASE_SIGNING_PRIVATE_KEY_B64 }}', 'production manifest signing secret is scoped to final release assembly')
assert(assembly['run'].include?('assemble-signed-release.sh'), 'release assembly invokes the tested four-platform manifest builder')
publisher = steps.find { |step| step['uses'].to_s.start_with?('softprops/action-gh-release@') }
assert(publisher && publisher.dig('with', 'files').include?('release-assets/*'), 'one final step publishes all full, delta and signed manifest assets together')

{
  'release-linux.yml' => 'dcp-linux-x64-package-release',
  'release-windows.yml' => 'dcp-windows-x64-package-release'
}.each do |file, artifact_name|
  workflow = load_workflow(file)
  assert(workflow_triggers(workflow).key?('workflow_call'), "#{file} is reusable and cannot publish independently")
  uploads = workflow.fetch('jobs').values.flat_map { |job| Array(job['steps']) }.select { |step| step['uses'].to_s.start_with?('actions/upload-artifact@') }
  assert(uploads.any? { |step| step.dig('with', 'name') == artifact_name }, "#{file} uploads its full package for atomic assembly")
  assert(workflow.fetch('jobs').values.flat_map { |job| Array(job['steps']) }.none? { |step| step['uses'].to_s.start_with?('softprops/action-gh-release@') }, "#{file} does not publish a partial release")
end

mac = load_workflow('release-macos.yml')
assert(workflow_triggers(mac).key?('workflow_call'), 'release-macos.yml is reusable and cannot publish independently')
mac_uploads = mac.fetch('jobs').fetch('macos-native-package').fetch('steps').select { |step| step['uses'].to_s.start_with?('actions/upload-artifact@') }
assert(mac_uploads.any? { |step| step.dig('with', 'name').to_s.include?('dcp-macos-') && step.dig('with', 'path').to_s.include?('.sha256') }, 'both native macOS architectures upload package and checksum')
assert(mac.fetch('jobs').values.flat_map { |job| Array(job['steps']) }.none? { |step| step['uses'].to_s.start_with?('softprops/action-gh-release@') }, 'macOS builder does not publish a partial release')
puts 'ALL RELEASE WORKFLOW CONTRACT TESTS PASSED'
