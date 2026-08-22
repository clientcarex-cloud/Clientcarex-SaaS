<?php if (empty($comparison['missing_in_dest']) && empty($comparison['missing_in_source']) && empty($comparison['schema_mismatch']) && empty($comparison['row_count_mismatch'])): ?>
    <div class="alert alert-success">Databases are identical!</div>
<?php else: ?>
    <div class="row">
        <?php if (!empty($comparison['missing_in_dest'])): ?>
        <div class="col-md-6">
            <div class="panel panel-danger">
                <div class="panel-heading">Missing in Destination</div>
                <div class="panel-body">
                    <ul>
                    <?php foreach ($comparison['missing_in_dest'] as $table): ?>
                        <li><?php echo $table; ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($comparison['missing_in_source'])): ?>
        <div class="col-md-6">
            <div class="panel panel-warning">
                <div class="panel-heading">Missing in Source</div>
                <div class="panel-body">
                    <ul>
                    <?php foreach ($comparison['missing_in_source'] as $table): ?>
                        <li><?php echo $table; ?></li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($comparison['schema_mismatch'])): ?>
    <div class="panel panel-warning">
        <div class="panel-heading">Schema Mismatches</div>
        <div class="panel-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Issue</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($comparison['schema_mismatch'] as $mismatch): ?>
                    <tr>
                        <td><?php echo $mismatch['table']; ?></td>
                        <td><?php echo $mismatch['issue']; ?></td>
                        <td>
                            <?php 
                            if(isset($mismatch['diff'])) echo "Diff: " . $mismatch['diff'];
                            if(isset($mismatch['source_cols'])) echo "Source Cols: " . $mismatch['source_cols'] . " vs Dest: " . $mismatch['dest_cols'];
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($comparison['row_count_mismatch'])): ?>
    <div class="panel panel-info">
        <div class="panel-heading">Row Count Differences</div>
        <div class="panel-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Source Rows</th>
                        <th>Dest Rows</th>
                        <th>Diff</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($comparison['row_count_mismatch'] as $mismatch): ?>
                    <tr>
                        <td><?php echo $mismatch['table']; ?></td>
                        <td><?php echo $mismatch['source_rows']; ?></td>
                        <td><?php echo $mismatch['dest_rows']; ?></td>
                        <td><?php echo abs($mismatch['source_rows'] - $mismatch['dest_rows']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>
