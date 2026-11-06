{ pkgs }:
let
  phpWithExtensions = pkgs.php.buildEnv {
    extensions = ({ enabled, all }: enabled ++ (with all; [ pdo_sqlite sqlite3 fileinfo ]));
  };
in {
  deps = [ phpWithExtensions ];
}
