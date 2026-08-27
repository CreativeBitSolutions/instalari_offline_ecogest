<div class="modal fade" id="helpModal" tabindex="-1" role="dialog" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="helpModalLabel">Ghiduri și Instrucțiuni</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                
                <?php 
                // Preluăm ID-ul clientului direct din sesiune
                $clientIdForHelp = $_SESSION['client_id'] ?? null;
                $hasGuides = false; // O variabilă pentru a verifica dacă s-a afișat vreun ghid
                ?>

                <div class="list-group">
                    
                    <?php if ($clientIdForHelp == 18): $hasGuides = true; ?>
                        <a href="ghiduri/comunicarescanner.pdf" target="_blank" class="list-group-item list-group-item-action">
                            <h5 class="mb-1">Nu funcționează scannerul?</h5>
                            <p class="mb-1">Ghid rapid pentru rezolvarea problemelor de comunicare cu scannerul de coduri de bare.</p>
                            <small>Click pentru a deschide documentul.</small>
                        </a>
                    <?php endif; ?>

                
                    
                    </div>

                <?php if (!$hasGuides): ?>
                    <p class="text-center text-muted mt-3">Nu există ghiduri disponibile pentru contul dumneavoastră.</p>
                <?php endif; ?>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Închide</button>
            </div>
        </div>
    </div>
</div>