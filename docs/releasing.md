# Publishing on Drupal.org

The contents of this module directory belong at the root of the public project
repository: `mave.info.yml`, `mave.module`, `mave.install`, `composer.json`,
`src/`, `config/`, `templates/`, `js/`, `css/`, tests, documentation and license.
The surrounding OrbStack workspace, `.local/`, site volumes, recordings and
SaaS connection scripts are development infrastructure and must not be published.

The project is [Mave Video](https://www.drupal.org/project/mave), with machine
name `mave`, Composer name `drupal/mave` and package type `drupal-module`.
Its Drupal repository is `git@git.drupal.org:project/mave.git`; GitHub remains
at `https://github.com/maveio/mave-drupal`.

## First public release

1. Create a personal Drupal.org account and verify its email. Open the profile's
   **DrupalCode access** tab, choose the Git username, then sign into
   `git.drupalcode.org` and accept its contributor terms. Register an SSH public
   key there (or configure a repository-scoped HTTPS token locally). Create a
   **Module project** on Drupal.org.
   Choose **Full project**; sandbox projects are deprecated. Add the maintainers,
   description, Drupal compatibility and links to documentation.
2. Follow the project's Version control instructions. The GitHub repository is
   `https://github.com/maveio/mave-drupal`; its default development branch is
   `main`. Add Drupal's repository as another remote and push a release branch
   such as `1.0.x`. Keep both repositories on the same reviewed source. The
   included GitLab CI file uses Drupal's official templates.
3. Let the hosted pipeline complete and resolve its failures. Test on a clean
   Drupal site, including browse, upload, editor preview and anonymous playback.
   Check slow/failing services and window resizing in a separate browser. Record
   the tested Drupal/PHP versions, service environment and browser-component
   version in the release notes. Identify any integration checks still pending
   in a beta. Before a stable release, verify the complete workflow against the
   intended production Mave endpoints with a pinned component build.
   Keep Composer installation in the README and source checkout instructions in
   CONTRIBUTING.md. Publish the same release commit to both repositories.
4. Start with a pre-release such as `1.0.0-beta1`. After tagging, create its
   release on the project page. Drupal generates the downloads and Composer
   distribution. Do not hard-code `version`, `project` or `datestamp` into
   `mave.info.yml`; Drupal's packaging adds release metadata.
5. Verify the published version resolves from
   `https://packages.drupal.org/8` in a clean Drupal project. A Git tag alone
   does not publish the Composer package; complete the Drupal.org release form
   and wait for its generated downloads and package metadata. Remove `@beta`
   from the README's installation command when publishing a stable release.

## Stable 1.0.0 release

The first beta is already published as `1.0.0-beta1`. Prepare `1.0.0` from
the updated source rather than renaming or moving that existing tag.

1. Review the Drupal 12 changes from [issue #3628107](https://www.drupal.org/project/mave/issues/3628107).
   The hooks use class implementations, with legacy shims for Drupal 11.0.
   Composer and `mave.info.yml` must declare the same core compatibility.
2. Require green GitHub and Drupal GitLab pipelines for the release commit.
   The GitHub matrix covers Drupal 11.4.9 and Drupal 12.0.0-beta1; the latter
   establishes compatibility with that prerelease, not a future final release.
3. Complete the browser smoke test from step 3 above, including a real upload,
   processing, selection, CKEditor preview and anonymous playback against the
   intended Mave endpoints. Record the component build and results. Automated
   module tests do not replace this integration check.
4. Change the README install command to `composer require 'drupal/mave:^1.0'`
   and remove its upcoming-release note in the release commit. Publish that
   same commit to GitHub and Drupal's
   `1.0.x` branch, tag `1.0.0`, then create a stable Drupal.org release. Use the
   prepared notes in [CHANGELOG.md](../CHANGELOG.md), updating their heading
   and adding the release date when published.
5. Verify a clean Composer installation of `drupal/mave:1.0.0`, module
   enablement and the media workflow. Set the release as recommended/supported
   on Drupal.org. Update the project description's Drupal/PHP requirements.

Keep the README's `@beta` command until the stable package is actually being
published; it still describes the currently available release.

The automated tests currently cover install-time media configuration, route
permissions and CSRF, key isolation, API validation and errors, JWT generation,
folder-first pagination and thumbnail resource limits. UI and full SaaS upload
verification still require the browser smoke test. Core version matrix results
come from the hosted pipeline; one local Drupal version does not prove all of ^11.

## Security advisory coverage

Publishing a project and obtaining Drupal Security Team advisory coverage are
separate steps. The warning means the project has not opted into Drupal's
advisory process; it is not itself a report of a vulnerability.

1. Sign into the personal account that committed the module's code. Check its
   profile for **Can opt projects into security advisory coverage**. If the
   permission is already present, no new application is needed.
2. Otherwise, apply once in the [security advisory coverage applications queue](https://www.drupal.org/project/projectapplications).
   Use the project branch containing the applicant's commits, not an issue
   fork. Enable GitLab CI, validate Composer metadata and resolve coding
   standards failures before requesting review. See the prepared
   [application text](security-coverage-application.md).
3. After approval, edit the Mave project and enable **Security advisory
   coverage**. Drupal requires a full project, an eligible maintainer with
   VCS write access, no open public issues tagged security, and a project at
   least ten days old. Opt-in cannot be undone by the maintainer.
4. Publish and support a stable release. Alpha, beta and RC releases do not
   receive advisory coverage. Verify the project and release actually show
   the coverage shield before changing any coverage claims in documentation.

A stable `1.0.0` can be published before review finishes, but it will not
remove the coverage warning by itself. Plan time for volunteer review, which
can take weeks; reviewing three other applications can qualify for priority.

## Official references

- [Create an account](https://www.drupal.org/user/register)
- [Obtain Git access](https://www.drupal.org/docs/develop/git/setting-up-git-for-drupal/obtaining-git-access)
- [Configure Git authentication](https://www.drupal.org/drupalorg/docs/user-accounts/git-authentication-for-drupalorg-projects)
- [Create a project](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/creating-a-new-project/how-to-create-a-new-project)
- [Create a release](https://www.drupal.org/docs/develop/git/git-for-drupal-project-maintainers/creating-a-project-release)
- [GitLab CI templates](https://project.pages.drupalcode.org/gitlab_templates/)
- [Security advisory coverage](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/security-coverage)
- [Apply for security coverage permission](https://www.drupal.org/docs/develop/managing-a-drupalorg-theme-module-or-distribution-project/security-coverage/opting-into/apply-for-the-permission-to-opt-into-security-advisory-coverage)
- [Repository and licensing policy](https://www.drupal.org/docs/develop/git/setting-up-git-for-drupal/drupal-git-usage-policies/drupal-git-contributor-agreement-repository-usage-policy)
- [Third-party libraries](https://www.drupal.org/docs/develop/git/setting-up-git-for-drupal/drupal-git-usage-policies/including-3rd-party-libraries)
