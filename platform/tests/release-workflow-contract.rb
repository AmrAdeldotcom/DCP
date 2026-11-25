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
assert(merge_guard && merge_guard['run'].include?('MAIN_SHA=') && merge_guard['run'].include?('GITHUB_SHA') && merge_guard['run'].include?('origin/$DEFAULT_BRANCH'), 'release signing requires the tag commit to equal the current default-branch HEAD')
base_step = steps.find { |step| step['name'].to_s.include?('preceding release packages') }
assert(base_step && base_step['run'].include?('v1.0.0-alpha.10') && base_step['run'].include?('BASE_TAG=""'), 'first approved release uses complete Full packages without a legacy base')
assert(base_step['run'].include?('GITHUB_REF_NAME" == "v1.0.0"') && base_step['run'].include?('BASE_TAG="v1.0.0-alpha.10"'), 'first stable v1.0.0 release uses Alpha 10 as its exact Delta base')
assembly_test = steps.find { |step| step['name'].to_s.include?('ephemeral test key') }
assert(assembly_test && assembly_test['run'].include?('platform/tests/release-assembly.sh'), 'release workflow runs a four-platform assembly test before using the production key')
assembly_source = File.read(File.join(ROOT, 'platform', 'tests', 'release-assembly.sh'))
assert(assembly_source.include?('v1.0.0 bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb') && assembly_source.include?('first stable v1.0.0 creates synchronized Delta packages directly from the approved Alpha 10 full packages'), 'release assembly regression covers the Alpha 10 to stable 1.0.0 Delta transition')
download = steps.find { |step| step['uses'].to_s.start_with?('actions/download-artifact@') }
assert(download && download.dig('with', 'pattern') == 'dcp-*-package-release', 'release assembly downloads only platform package artifacts')
assembly = steps.find { |step| step['name'].to_s.include?('Build and sign synchronized') }
assert(assembly && assembly.dig('env', 'DCP_RELEASE_SIGNING_PRIVATE_KEY_B64') == '${{ secrets.DCP_RELEASE_SIGNING_PRIVATE_KEY_B64 }}', 'production manifest signing secret is scoped to final release assembly')
assert(assembly['run'].include?('assemble-signed-release.sh'), 'release assembly invokes the tested four-platform manifest builder')
publisher = steps.find { |step| step['uses'].to_s.start_with?('softprops/action-gh-release@') }
assert(publisher && publisher.dig('with', 'files').include?('release-assets/*'), 'one final step publishes all full, delta and signed manifest assets together')
assert(publisher.dig('with', 'prerelease').to_s.include?('contains(github.ref_name'), 'semantic prerelease tags remain prereleases while v1.0.0 is published as stable')
assert(publisher.dig('with', 'make_latest').to_s.include?('!contains(github.ref_name'), 'stable releases become GitHub latest while alpha/beta builds do not replace latest')

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
arm64 = load_workflow('build-llama-macos-arm64.yml')
arm64_text = arm64.fetch('jobs').values.flat_map { |job| Array(job['steps']) }.map { |step| step['run'].to_s }.join("\n")
assert(arm64_text.include?('clone-pinned-source.sh') && arm64_text.include?('openssl-3.5.4') && arm64_text.include?('b12655'), 'Apple Silicon OpenSSL and llama.cpp source fetches use bounded retry helper')
php = load_workflow('build-php-runtime-macos.yml')
php_text = php.fetch('jobs').values.flat_map { |job| Array(job['steps']) }.map { |step| step['run'].to_s }.join("\n")
assert(php_text.include?('clone-pinned-source.sh') && php_text.include?('for attempt in 1 2 3 4 5'), 'managed PHP source checkout and official PHP download use bounded retries')
cleanup = load_workflow('cleanup-legacy-github.yml')
cleanup_trigger = workflow_triggers(cleanup)
assert(cleanup_trigger.dig('workflow_run', 'workflows') == ['Build and publish DCP signed cross-platform release'], 'legacy cleanup runs after the unified signed release workflow')
assert(cleanup_trigger.key?('workflow_dispatch') && cleanup_trigger.dig('push', 'branches') == ['main'], 'cleanup can be manually retried and is rerun when its main-branch workflow is fixed')
cleanup_job = cleanup.fetch('jobs').fetch('cleanup-after-stable-release')
assert(cleanup_job['if'].to_s.include?("head_branch == 'v1.0.0'") && cleanup_job['if'].to_s.include?("conclusion == 'success'") && cleanup_job['if'].to_s.include?("event_name == 'push'"), 'cleanup is gated on successful stable publication or an explicit repair/dispatch trigger')
assert(cleanup.dig('permissions', 'contents') == 'write' && cleanup.dig('permissions', 'actions') == 'write', 'cleanup receives only the repository and Actions write permissions it needs')
cleanup_script = cleanup_job.fetch('steps').first.dig('with', 'script')
assert(cleanup_script.include?('getReleaseByTag') && cleanup_script.include?('prerelease'), 'cleanup verifies the stable release is published and not a prerelease')
assert(cleanup_script.include?('deleteRelease') && cleanup_script.include?('deleteRef') && cleanup_script.include?('deleteWorkflowRun'), 'cleanup removes superseded prereleases, Alpha/Beta tags and completed failed CI runs')
assert(cleanup_script.include?('DCP-macOS-Intel-update-v3.json.sig') && cleanup_script.include?('DCP-Windows-update-v3.json') && cleanup_script.include?('DCP-macOS-AppleSilicon.zip.sha256'), 'cleanup refuses to run until all four signed platform manifests exist')
assert(cleanup_script.include?('for (let page = 1; page <= 100; page++)') && cleanup_script.include?('response.data.workflow_runs') && !cleanup_script.include?('response => response.data.workflow_runs'), 'cleanup manually paginates the Actions workflow_runs response without the broken paginate mapper')
assert(cleanup_script.include?('stablePublishedAt') && cleanup_script.include?('created_at') && cleanup_script.include?('cleanupWorkflowName'), 'cleanup removes pre-stable failures and its own prior failures but preserves unrelated post-stable failures')
assert(cleanup_script.include?("ref: 'tags/v'") && cleanup_script.include?('(alpha|beta)'), 'cleanup discovers and removes Alpha and Beta tags across semantic versions')
puts 'ALL RELEASE WORKFLOW CONTRACT TESTS PASSED'
